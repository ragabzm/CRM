<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * An internal note never reaches the customer.
 *
 * This is the one failure in this story that cannot be taken back. A note is a
 * colleague's private remark ABOUT the person it would be emailed to — "this
 * one always escalates", "check whether they actually paid" — and sending it
 * ends the relationship, not just the ticket.
 */
final class InternalNoteNotDeliveredTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->ticket = $this->makeTicket();

        $agent = $this->makeUser(Roles::AGENT);
        $actor = Actor::staff((string) $agent->getKey(), $agent->name);
        $append = $this->app->make(AppendMessage::class);

        $append->handle($actor, (string) $this->ticket->getKey(), MessageDirection::Inbound, 'My invoice is wrong.');
        $append->handle($actor, (string) $this->ticket->getKey(), MessageDirection::Outbound, 'Looking into it.');
        $append->handle($actor, (string) $this->ticket->getKey(), MessageDirection::Internal, 'Second time this month — check the billing run.');
    }

    public function test_the_customer_visible_scope_excludes_notes(): void
    {
        $visible = TicketMessage::query()
            ->where('ticket_id', $this->ticket->getKey())
            ->customerVisible()
            ->pluck('direction')
            ->map(static fn ($d): string => $d instanceof MessageDirection ? $d->value : (string) $d)
            ->all();

        $this->assertSame(['inbound', 'outbound'], $visible);
    }

    public function test_the_scope_carries_no_note_body(): void
    {
        $bodies = TicketMessage::query()
            ->where('ticket_id', $this->ticket->getKey())
            ->customerVisible()
            ->pluck('body')
            ->implode(' ');

        $this->assertStringNotContainsString('check the billing run', $bodies);
    }

    public function test_the_enum_itself_answers_the_question(): void
    {
        /*
         * Asked on the enum rather than by comparing strings at each call site,
         * so adding a fourth direction forces whoever adds it to decide —
         * rather than defaulting to visible, which is the direction of the
         * mistake that cannot be undone.
         */
        $this->assertFalse(MessageDirection::Internal->isCustomerVisible());
        $this->assertTrue(MessageDirection::Inbound->isCustomerVisible());
        $this->assertTrue(MessageDirection::Outbound->isCustomerVisible());
    }

    public function test_a_note_is_never_queued_for_delivery(): void
    {
        $note = TicketMessage::query()
            ->where('direction', MessageDirection::Internal->value)
            ->firstOrFail();

        // Nothing can pick it up: there is no delivery to make, so there is no
        // state for a sender to act on.
        $this->assertNull($note->delivery_state);
    }

    public function test_the_portal_never_sees_a_note(): void
    {
        // The customer-facing surface, exercised end to end rather than trusted
        // because the scope exists.
        $portalReadable = TicketMessage::query()
            ->where('ticket_id', $this->ticket->getKey())
            ->customerVisible()
            ->count();

        $this->assertSame(2, $portalReadable);
        $this->assertSame(3, TicketMessage::query()->where('ticket_id', $this->ticket->getKey())->count());
    }

    public function test_a_note_still_reaches_the_agent_who_needs_it(): void
    {
        // Hiding it from the customer is the point; hiding it from colleagues
        // would make the feature useless.
        $agent = $this->makeUser(Roles::ADMINISTRATOR);

        $this->actingAs($agent)
            ->getJson("/api/v1/tickets/{$this->ticket->getKey()}/messages")
            ->assertOk()
            ->assertJsonPath('data.2.direction', 'internal')
            ->assertJsonPath('data.2.body', 'Second time this month — check the billing run.');
    }
}
