<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * The sweep that closes tickets the customer has stopped replying to.
 *
 * It runs through the same ChangeStatus command a person uses — never a raw
 * UPDATE — so an auto-close is indistinguishable in shape from a human one and
 * every rule still applies.
 */
final class TicketAutoCloseSweepTest extends TestCase
{
    use InteractsWithLifecycle;
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTickets();
    }

    /** A resolved ticket, aged as described. */
    private function resolvedTicket(int $hoursAgo, ?int $customerActivityHoursAgo = null): Ticket
    {
        $ticket = $this->makeTicket();

        $ticket->forceFill([
            'status' => TicketStatus::Resolved->value,
            'resolved_at' => now()->subHours($hoursAgo),
            'last_customer_activity_at' => $customerActivityHoursAgo === null
                ? null
                : now()->subHours($customerActivityHoursAgo),
        ])->save();

        return $ticket->refresh();
    }

    private function sweep(array $options = []): void
    {
        Artisan::call('tickets:auto-close', $options);
    }

    public function test_a_ticket_resolved_past_the_window_closes(): void
    {
        $ticket = $this->resolvedTicket(hoursAgo: 73);

        $this->sweep();

        $this->assertSame(TicketStatus::Closed, $ticket->refresh()->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_a_ticket_still_inside_the_window_is_left_alone(): void
    {
        $ticket = $this->resolvedTicket(hoursAgo: 71);

        $this->sweep();

        // 71 against a 72-hour window. Off-by-one here closes a customer's
        // ticket an hour before they were going to reply.
        $this->assertSame(TicketStatus::Resolved, $ticket->refresh()->status);
    }

    public function test_a_recent_customer_reply_keeps_it_open(): void
    {
        $ticket = $this->resolvedTicket(hoursAgo: 100, customerActivityHoursAgo: 1);

        $this->sweep();

        /*
         * The resolution is old but the customer spoke an hour ago. Closing on
         * `resolved_at` alone would end a conversation that is still running.
         */
        $this->assertSame(TicketStatus::Resolved, $ticket->refresh()->status);
    }

    public function test_an_old_customer_reply_does_not_keep_it_open(): void
    {
        $ticket = $this->resolvedTicket(hoursAgo: 100, customerActivityHoursAgo: 100);

        $this->sweep();

        $this->assertSame(TicketStatus::Closed, $ticket->refresh()->status);
    }

    public function test_an_already_closed_ticket_is_not_touched_again(): void
    {
        $ticket = $this->resolvedTicket(hoursAgo: 100);
        $this->sweep();

        $closedAt = $ticket->refresh()->closed_at;
        $version = $ticket->version;

        $this->sweep();

        // Idempotent: nothing moved, and the version did not creep.
        $ticket->refresh();
        $this->assertEquals($closedAt, $ticket->closed_at);
        $this->assertSame($version, $ticket->version);
    }

    public function test_an_open_ticket_is_never_swept(): void
    {
        $ticket = $this->makeTicket();

        $this->sweep();

        $this->assertSame(TicketStatus::Open, $ticket->refresh()->status);
    }

    public function test_running_it_twice_gives_the_same_result(): void
    {
        $closes = $this->resolvedTicket(hoursAgo: 100);
        $stays = $this->resolvedTicket(hoursAgo: 10);

        $this->sweep();
        $this->sweep();

        $this->assertSame(TicketStatus::Closed, $closes->refresh()->status);
        $this->assertSame(TicketStatus::Resolved, $stays->refresh()->status);
        $this->assertSame(1, TicketEvent::query()
            ->where('ticket_id', $closes->getKey())
            ->where('event_type', TicketEvent::STATUS_CHANGED)
            ->count());
    }

    public function test_the_entry_names_the_system_and_the_reason(): void
    {
        $ticket = $this->resolvedTicket(hoursAgo: 100);

        $this->sweep();

        $event = TicketEvent::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', TicketEvent::STATUS_CHANGED)
            ->firstOrFail();

        /*
         * Same shape as a human action, differing only in who acted. "The
         * system closed it" would explain nothing; the reason names the job to
         * go and look at.
         */
        $this->assertSame('system', $event->actor_type);
        $this->assertSame('auto_close', $event->actor_reason);
        $this->assertNull($event->actor_id);
        $this->assertSame((int) $ticket->refresh()->version, $event->version_after);
    }

    public function test_the_sweep_is_exempt_from_the_version_check(): void
    {
        /*
         * The sweep submits no version at all — it has none to submit. If the
         * guard treated that as stale, the sweep would refuse every ticket and
         * auto-close would silently never work.
         */
        $ticket = $this->resolvedTicket(hoursAgo: 100);
        $this->assertGreaterThan(0, $ticket->version);

        $this->sweep();

        $this->assertSame(TicketStatus::Closed, $ticket->refresh()->status);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $ticket = $this->resolvedTicket(hoursAgo: 100);

        $this->sweep(['--dry-run' => true]);

        $this->assertSame(TicketStatus::Resolved, $ticket->refresh()->status);
        $this->assertNull($ticket->closed_at);
    }

    public function test_the_limit_is_honoured(): void
    {
        $this->resolvedTicket(hoursAgo: 100);
        $this->resolvedTicket(hoursAgo: 99);
        $this->resolvedTicket(hoursAgo: 98);

        $this->sweep(['--limit' => 1]);

        // A run must be bounded, or one sweep on a large backlog holds a
        // worker for minutes.
        $this->assertSame(1, Ticket::query()->where('status', TicketStatus::Closed->value)->count());
    }

    public function test_a_ticket_reopened_mid_sweep_is_skipped_not_fatal(): void
    {
        $stale = $this->resolvedTicket(hoursAgo: 100);
        $fine = $this->resolvedTicket(hoursAgo: 99);

        // Somebody reopened it between the select and the dispatch.
        $stale->forceFill(['status' => TicketStatus::Open->value])->save();

        $this->sweep();

        // The transition check refuses it, correctly — and the other ticket
        // still closes. One awkward row must not stop the batch.
        $this->assertSame(TicketStatus::Open, $stale->refresh()->status);
        $this->assertSame(TicketStatus::Closed, $fine->refresh()->status);
    }

    public function test_the_window_follows_the_setting(): void
    {
        $ticket = $this->resolvedTicket(hoursAgo: 30);

        $this->sweep();
        $this->assertSame(TicketStatus::Resolved, $ticket->refresh()->status);

        $this->app->make(\App\Modules\Platform\Support\Settings\SettingsRegistry::class)
            ->set(\App\Modules\Tickets\Domain\Lifecycle\TicketLifecycle::AUTO_CLOSE_SETTING, 24, null);

        $this->sweep();
        $this->assertSame(TicketStatus::Closed, $ticket->refresh()->status);
    }
}
