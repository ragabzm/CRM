<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The conversation obeys the same row-level rule as the ticket.
 *
 * It did not. `can.capability:ticket.read` on the route says an agent may read
 * tickets — not that they may read THIS one — and the messages endpoint checked
 * nothing else. `GET /tickets/{id}` correctly answered 404 for a colleague's
 * ticket while `GET /tickets/{id}/messages` returned the entire conversation on
 * the same ticket, which is the most sensitive thing it holds.
 */
final class TicketMessageVisibilityTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private const SECRET = 'Card ending 4417 was charged twice.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function ticketWithAMessage(int $assigneeId): Ticket
    {
        $ticket = $this->makeTicket(['assignee_id' => $assigneeId]);

        $message = new TicketMessage;
        $message->forceFill([
            'ticket_id' => $ticket->getKey(),
            'customer_id' => $ticket->customer_id,
            'direction' => 'inbound',
            'author_type' => 'customer',
            'author_id' => (string) $ticket->customer_id,
            'author_name' => 'Someone',
            'body' => self::SECRET,
            'sent_at' => now(),
        ])->save();

        return $ticket;
    }

    public function test_an_outsider_agent_cannot_read_the_conversation(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $outsider = $this->makeUser(Roles::AGENT);

        $ticket = $this->ticketWithAMessage((int) $owner->getKey());

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/tickets/{$ticket->getKey()}/messages");

        // 404, matching what GET /tickets/{id} already answered. A 403 would
        // confirm the ticket exists.
        $response->assertStatus(404)->assertJsonPath('code', 'tickets.not_found');
        $this->assertStringNotContainsString(self::SECRET, (string) $response->getContent());
    }

    public function test_the_assigned_agent_can_read_it(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $ticket = $this->ticketWithAMessage((int) $owner->getKey());

        $this->actingAs($owner)
            ->getJson("/api/v1/tickets/{$ticket->getKey()}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.body', self::SECRET);
    }

    public function test_a_supervisor_can_read_any_conversation(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $ticket = $this->ticketWithAMessage((int) $owner->getKey());

        // Supervision that cannot see the queue is not supervision.
        $this->actingAs($this->makeUser(Roles::SUPERVISOR))
            ->getJson("/api/v1/tickets/{$ticket->getKey()}/messages")
            ->assertOk();
    }

    public function test_an_outsider_agent_cannot_write_into_the_conversation(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $outsider = $this->makeUser(Roles::AGENT);

        $ticket = $this->ticketWithAMessage((int) $owner->getKey());

        // Writing into a ticket you cannot see is the same leak one step
        // further: it puts your words in someone else's conversation.
        $this->actingAs($outsider)
            ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
            ->postJson("/api/v1/tickets/{$ticket->getKey()}/messages", ['body' => 'Hello'])
            ->assertStatus(404);

        $this->assertSame(1, TicketMessage::query()->where('ticket_id', $ticket->getKey())->count());
    }

    public function test_the_history_obeys_the_same_rule(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $outsider = $this->makeUser(Roles::AGENT);

        $ticket = $this->ticketWithAMessage((int) $owner->getKey());

        // One rule, applied at every query site that reads a ticket's contents.
        $this->actingAs($outsider)
            ->getJson("/api/v1/tickets/{$ticket->getKey()}/events")
            ->assertStatus(404);
    }
}
