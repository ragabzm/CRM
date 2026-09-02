<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Events\CustomerReplyPosted;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * A customer replying to a resolved ticket means it was not resolved.
 *
 * They are the only party who can really say so, and making them wait for an
 * agent to notice is how a "resolved" ticket sits for three days while the
 * customer assumes somebody is reading it.
 */
final class CustomerReplyReopensTest extends TestCase
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

    private function reply(Ticket $ticket): void
    {
        event(new CustomerReplyPosted((string) $ticket->getKey()));
    }

    private function resolved(): Ticket
    {
        $ticket = $this->makeTicket();
        $ticket->forceFill([
            'status' => TicketStatus::Resolved->value,
            'resolved_at' => now()->subDays(1),
        ])->save();

        return $ticket->refresh();
    }

    public function test_a_reply_reopens_a_resolved_ticket(): void
    {
        $ticket = $this->resolved();

        $this->reply($ticket);

        $this->assertSame(TicketStatus::Open, $ticket->refresh()->status);
    }

    public function test_reopening_clears_the_resolution(): void
    {
        $ticket = $this->resolved();

        $this->reply($ticket);

        // Otherwise the sweep would close it again on its next run.
        $this->assertNull($ticket->refresh()->resolved_at);
    }

    public function test_the_reply_is_stamped(): void
    {
        $ticket = $this->resolved();

        $this->reply($ticket);

        $this->assertNotNull($ticket->refresh()->last_customer_activity_at);
    }

    public function test_the_stamp_lands_even_when_the_status_does_not_change(): void
    {
        $ticket = $this->makeTicket();

        $this->reply($ticket);

        // An open ticket does not transition, but the customer still spoke —
        // and the sweep reads this column later.
        $ticket->refresh();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNotNull($ticket->last_customer_activity_at);
    }

    public function test_a_reply_beats_the_sweep_that_was_about_to_close_it(): void
    {
        $ticket = $this->makeTicket();
        $ticket->forceFill([
            'status' => TicketStatus::Resolved->value,
            // Old enough that the sweep would close it.
            'resolved_at' => now()->subHours(100),
        ])->save();

        $this->reply($ticket->refresh());

        Artisan::call('tickets:auto-close');

        /*
         * The listener stamps activity BEFORE transitioning, so a sweep running
         * immediately afterwards sees a customer who has just spoken and leaves
         * the ticket alone. Stamping after would leave a window in which the
         * sweep closes a ticket the customer is actively writing on.
         */
        $this->assertSame(TicketStatus::Open, $ticket->refresh()->status);
    }

    public function test_a_closed_ticket_is_not_reopened_by_a_reply(): void
    {
        $ticket = $this->makeTicket();
        $ticket->forceFill([
            'status' => TicketStatus::Closed->value,
            'closed_at' => now()->subDay(),
        ])->save();

        $this->reply($ticket->refresh());

        /*
         * Closing is an agreed ending. A stray "thanks!" three weeks later must
         * not silently restart it — reopening a closed ticket needs a person.
         */
        $this->assertSame(TicketStatus::Closed, $ticket->refresh()->status);
        // The reply is still recorded, though.
        $this->assertNotNull($ticket->last_customer_activity_at);
    }

    public function test_the_reopen_is_recorded_as_a_system_action(): void
    {
        $ticket = $this->resolved();

        $this->reply($ticket);

        $event = TicketEvent::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', TicketEvent::STATUS_CHANGED)
            ->firstOrFail();

        $this->assertSame('system', $event->actor_type);
        $this->assertSame('customer_reply', $event->actor_reason);
    }

    public function test_a_reply_about_a_ticket_that_does_not_exist_is_harmless(): void
    {
        // Queued work outliving its subject is ordinary, not exceptional.
        event(new CustomerReplyPosted('01JZZZZZZZZZZZZZZZZZZZZZZZ'));

        $this->assertTrue(true);
    }
}
