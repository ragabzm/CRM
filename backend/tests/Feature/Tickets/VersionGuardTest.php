<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Concurrency\VersionGuard;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Two people editing one ticket.
 *
 * The failure this prevents: one agent resolves a ticket while another, looking
 * at a screen loaded a minute earlier, raises its priority. Without the guard
 * the second write silently reverts the first and nobody finds out until the
 * customer asks why their resolved ticket is open again.
 */
final class VersionGuardTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    private array $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTickets(Roles::SUPERVISOR);
        $this->ticket = $this->createTicket();
    }

    private function patchTicket(array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->patchJson("/api/v1/tickets/{$this->ticket['id']}", $body);
    }

    public function test_a_change_with_the_current_version_succeeds_and_bumps_it(): void
    {
        $this->patchTicket(['version' => 1, 'priority' => 'high'])
            ->assertOk()
            ->assertJsonPath('priority', 'high')
            // Exactly one, so the next writer's version means something.
            ->assertJsonPath('version', 2);
    }

    public function test_a_stale_version_is_refused(): void
    {
        $this->patchTicket(['version' => 1, 'priority' => 'high'])->assertOk();

        $response = $this->patchTicket(['version' => 1, 'status' => 'pending'])->assertStatus(409);

        $response->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'tickets.stale_version');
    }

    public function test_the_refusal_carries_everything_needed_to_recover(): void
    {
        $this->patchTicket(['version' => 1, 'priority' => 'high'])->assertOk();

        $response = $this->patchTicket(['version' => 1, 'status' => 'pending'])->assertStatus(409);

        // The client's next move is always "show me what it says now", and
        // making them fetch again costs a round trip and a window in which it
        // changes a third time.
        $response->assertJsonPath('current_version', 2)
            ->assertJsonPath('submitted_version', 1)
            ->assertJsonPath('current.priority', 'high')
            ->assertJsonPath('current.status', 'open')
            ->assertJsonPath('ticket_id', $this->ticket['id']);

        // All five contended values, so one round trip repopulates the form.
        foreach (VersionGuard::CONTENDED as $field) {
            $this->assertArrayHasKey($field, $response->json('current'));
        }
    }

    public function test_a_refused_change_writes_nothing_at_all(): void
    {
        $this->patchTicket(['version' => 1, 'priority' => 'high'])->assertOk();
        $eventsBefore = TicketEvent::query()->count();

        $this->patchTicket(['version' => 1, 'status' => 'pending', 'priority' => 'low'])->assertStatus(409);

        $ticket = Ticket::query()->findOrFail($this->ticket['id']);

        // No partial write: not the status, not the priority, not an event.
        $this->assertSame('open', $ticket->status->value);
        $this->assertSame('high', $ticket->priority->value);
        $this->assertSame(2, $ticket->version);
        $this->assertSame($eventsBefore, TicketEvent::query()->count());
    }

    public function test_each_changed_attribute_gets_its_own_event(): void
    {
        $this->patchTicket(['version' => 1, 'priority' => 'high', 'department_id' => $this->departmentId])->assertOk();

        $types = TicketEvent::query()
            ->where('ticket_id', $this->ticket['id'])
            ->pluck('event_type')
            ->all();

        // "Priority changed" and "department changed" are two facts a reader
        // filters on separately; one combined row would be unfilterable.
        $this->assertContains(TicketEvent::PRIORITY_CHANGED, $types);
        $this->assertContains(TicketEvent::DEPARTMENT_CHANGED, $types);
    }

    public function test_an_event_records_what_it_changed_from_and_to(): void
    {
        $this->patchTicket(['version' => 1, 'priority' => 'urgent'])->assertOk();

        $event = TicketEvent::query()
            ->where('event_type', TicketEvent::PRIORITY_CHANGED)
            ->firstOrFail();

        $this->assertSame('normal', $event->payload['from']);
        $this->assertSame('urgent', $event->payload['to']);
        $this->assertSame(2, $event->version_after);
    }

    public function test_the_version_only_moves_once_per_request(): void
    {
        $this->patchTicket(['version' => 1, 'priority' => 'high', 'status' => 'pending'])
            ->assertOk()
            // Two attributes, one bump: the version counts REQUESTS, and a
            // client that submitted 1 must be able to submit 2 next.
            ->assertJsonPath('version', 2);
    }

    public function test_an_empty_change_set_is_refused(): void
    {
        $this->patchTicket(['version' => 1])->assertStatus(422)->assertJsonPath('code', 'tickets.no_changes');
    }

    public function test_a_change_without_a_version_is_refused(): void
    {
        // Omitting it would be silently opting out of the protection.
        $this->patchTicket(['priority' => 'high'])->assertStatus(422);
    }

    public function test_the_guard_is_scoped_to_contended_properties_only(): void
    {
        /*
         * Pins the intent so a future author does not "helpfully" widen it.
         * Guarding every column would make a message append fail whenever
         * anyone else touched the ticket — the normal case on a busy ticket —
         * and train people to retry blindly.
         */
        $this->assertSame(
            ['status', 'priority', 'category_id', 'assignee_id', 'department_id'],
            VersionGuard::CONTENDED,
        );

        $guard = new VersionGuard;
        $ticket = Ticket::query()->findOrFail($this->ticket['id']);

        // An uncontended change with a hopelessly stale version passes.
        $guard->apply($ticket, 999, ['subject' => 'Renamed']);

        $this->expectException(\App\Modules\Platform\Exceptions\ProblemException::class);
        $guard->apply($ticket, 999, ['status' => 'pending']);
    }

    public function test_a_missing_ticket_is_a_404(): void
    {
        $this->withIdempotencyKey()
            ->patchJson('/api/v1/tickets/01JZZZZZZZZZZZZZZZZZZZZZZZ', ['version' => 1, 'priority' => 'high'])
            ->assertStatus(404);
    }
}
