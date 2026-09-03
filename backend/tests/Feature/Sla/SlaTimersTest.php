<?php

declare(strict_types=1);

namespace Tests\Feature\Sla;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Sla\Domain\SlaClock;
use App\Modules\Sla\Domain\SlaState;
use App\Modules\Sla\Domain\TicketTimelineLoader;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Commands\TicketAttributeChanges;
use App\Modules\Tickets\Domain\Commands\UpdateTicketAttributes;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The two clocks, and why they run differently.
 *
 * RESPONSE is a promise kept or broken once — "somebody will get back to you".
 * RESOLUTION is a promise about the desk's own work, so it stops while the ball
 * is in the customer's court.
 */
final class SlaTimersTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $s = $this->app->make(SettingsRegistry::class);
        $s->set('email.enabled', false, null);
        $s->set('sla.timezone', 'Asia/Riyadh', null);
        // One working hour to respond, four to resolve — small numbers so the
        // arithmetic in each test is checkable by eye.
        $s->set('sla.response_target_seconds.normal', 3600, null);
        $s->set('sla.resolution_target_seconds.normal', 14400, null);
        $s->set('sla.at_risk_threshold_percent', 80, null);
    }

    private function riyadh(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Asia/Riyadh')->setTimezone('UTC');
    }

    private function ticketRaisedAt(string $local): Ticket
    {
        $ticket = $this->makeTicket(['priority' => 'normal']);
        $ticket->forceFill(['created_at' => $this->riyadh($local)])->save();

        return $ticket->refresh();
    }

    private function read(Ticket $ticket, string $nowLocal): array
    {
        $timeline = $this->app->make(TicketTimelineLoader::class)->forTicket((string) $ticket->getKey());

        return $this->app->make(SlaClock::class)->read($timeline, $this->riyadh($nowLocal));
    }

    private function agentReplies(Ticket $ticket, string $localAt): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $message = $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            MessageDirection::Outbound,
            'Looking into it.',
        );

        DB::table('ticket_messages')->where('id', $message->getKey())
            ->update(['sent_at' => $this->riyadh($localAt)]);
    }

    public function test_a_fresh_ticket_is_on_track(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');

        $reading = $this->read($ticket, '2026-09-06 09:10')[SlaClock::RESPONSE];

        $this->assertSame(SlaState::OnTrack, $reading->state);
        $this->assertSame(10, $reading->elapsedMinutes);
        $this->assertSame(50, $reading->remainingMinutes);
    }

    public function test_it_warns_before_it_breaches(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');

        // 48 of 60 minutes is exactly the 80% threshold.
        $reading = $this->read($ticket, '2026-09-06 09:48')[SlaClock::RESPONSE];

        // A warning that arrives with the breach is not a warning.
        $this->assertSame(SlaState::AtRisk, $reading->state);
    }

    public function test_it_breaches_once_the_target_passes(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');

        $reading = $this->read($ticket, '2026-09-06 10:30')[SlaClock::RESPONSE];

        $this->assertSame(SlaState::Breached, $reading->state);
        $this->assertSame(-30, $reading->remainingMinutes);
    }

    public function test_the_clock_does_not_run_overnight(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 16:50');

        $reading = $this->read($ticket, '2026-09-07 09:20')[SlaClock::RESPONSE];

        /*
         * Sixteen and a half hours on the wall, thirty working minutes. A
         * customer would be astonished to be told their ticket was late
         * overnight.
         */
        $this->assertSame(30, $reading->elapsedMinutes);
        $this->assertSame(SlaState::OnTrack, $reading->state);
    }

    public function test_a_reply_stops_the_response_clock(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');
        $this->agentReplies($ticket, '2026-09-06 09:30');

        $reading = $this->read($ticket, '2026-09-06 15:00')[SlaClock::RESPONSE];

        // Met on the evidence of when the reply went, not where the clock
        // would be now.
        $this->assertSame(SlaState::Met, $reading->state);
        $this->assertSame(30, $reading->elapsedMinutes);
    }

    public function test_a_late_reply_is_recorded_as_breached_forever(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');
        $this->agentReplies($ticket, '2026-09-06 11:00');

        $reading = $this->read($ticket, '2026-09-08 11:00')[SlaClock::RESPONSE];

        // Two days later it is still a breach. The promise was broken once.
        $this->assertSame(SlaState::Breached, $reading->state);
        $this->assertSame(120, $reading->elapsedMinutes);
    }

    public function test_the_response_clock_never_restarts(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');
        $this->agentReplies($ticket, '2026-09-06 09:30');
        $this->agentReplies($ticket, '2026-09-06 10:30');

        $reading = $this->read($ticket, '2026-09-06 15:00')[SlaClock::RESPONSE];

        /*
         * Still 30. Restarting on a later message would let a desk that
         * answered in four minutes and then went silent for a week look
         * permanently on track.
         */
        $this->assertSame(30, $reading->elapsedMinutes);
    }

    public function test_an_internal_note_does_not_count_as_a_reply(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');
        $agent = $this->makeUser(Roles::AGENT);

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            MessageDirection::Internal,
            'Second time this month.',
        );

        $reading = $this->read($ticket, '2026-09-06 10:30')[SlaClock::RESPONSE];

        // The customer never saw it. Counting it would let a desk meet every
        // response target by talking to itself.
        $this->assertSame(SlaState::Breached, $reading->state);
    }

    public function test_the_resolution_clock_pauses_while_waiting_on_the_customer(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');
        $this->transitionAt($ticket, 'pending', '2026-09-06 10:00');

        $reading = $this->read($ticket, '2026-09-06 16:00')[SlaClock::RESOLUTION];

        /*
         * One working hour elapsed before the pause; the six hours since were
         * the customer's. Counting them would make the number measure the
         * customer rather than the service.
         */
        $this->assertSame(60, $reading->elapsedMinutes);
        $this->assertSame(SlaState::Paused, $reading->state);
    }

    public function test_the_resolution_clock_resumes(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');

        $this->transitionAt($ticket, 'pending', '2026-09-06 10:00');
        $this->transitionAt($ticket, 'open', '2026-09-06 14:00');

        $reading = $this->read($ticket, '2026-09-06 15:00')[SlaClock::RESOLUTION];

        // One hour before the pause, one hour after. The four in between were
        // the customer's.
        $this->assertSame(120, $reading->elapsedMinutes);
        $this->assertSame(SlaState::OnTrack, $reading->state);
    }

    public function test_resolving_stops_the_clock(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');
        $ticket->forceFill(['resolved_at' => $this->riyadh('2026-09-06 11:00')])->save();

        $reading = $this->read($ticket->refresh(), '2026-09-08 11:00')[SlaClock::RESOLUTION];

        $this->assertSame(SlaState::Met, $reading->state);
        $this->assertSame(120, $reading->elapsedMinutes);
    }

    public function test_a_paused_ticket_is_not_reported_as_on_track(): void
    {
        $ticket = $this->ticketRaisedAt('2026-09-06 09:00');
        $this->transitionAt($ticket, 'pending', '2026-09-06 09:05');

        $reading = $this->read($ticket, '2026-09-06 09:30')[SlaClock::RESOLUTION];

        // An agent looking at a paused ticket should not be pushed to act on
        // work that is not theirs to do.
        $this->assertSame(SlaState::Paused, $reading->state);
    }

    /**
     * Moves the ticket through the real command, then backdates the history
     * entry the loader replays.
     */
    private function transitionAt(Ticket $ticket, string $status, string $localAt): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        /*
         * The ids that existed BEFORE, so the new event can be found by
         * difference. Ordering by `created_at` would pick the wrong row here:
         * these tests place events on dates in 2026, so an already-backdated
         * event sorts after one written at the real clock time.
         */
        $before = DB::table('ticket_events')->where('ticket_id', $ticket->getKey())->pluck('id')->all();

        $this->app->make(UpdateTicketAttributes::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            $ticket->refresh()->version,
            TicketAttributeChanges::of(['status' => $status]),
        );

        DB::table('ticket_events')
            ->where('ticket_id', $ticket->getKey())
            ->whereNotIn('id', $before === [] ? [''] : $before)
            ->update(['created_at' => $this->riyadh($localAt)]);
    }
}
