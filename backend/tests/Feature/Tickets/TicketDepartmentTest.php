<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Department;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class TicketDepartmentTest extends TestCase
{
    use InteractsWithLifecycle;
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    private int $otherDepartment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTickets();
        $this->otherDepartment = (int) Department::create(['name' => 'Support', 'is_active' => true])->getKey();
    }

    private function move(Ticket $ticket, int $departmentId): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->patchJson("/api/v1/tickets/{$ticket->getKey()}/department", [
            'version' => (int) $ticket->refresh()->version,
            'department_id' => $departmentId,
        ]);
    }

    public function test_a_ticket_moves_to_another_department(): void
    {
        $this->actingAs($this->supervisor());
        $ticket = $this->makeTicket(departmentId: $this->departmentId);

        $this->move($ticket, $this->otherDepartment)
            ->assertOk()
            ->assertJsonPath('department_id', $this->otherDepartment);
    }

    public function test_the_move_is_recorded(): void
    {
        $this->actingAs($this->supervisor());
        $ticket = $this->makeTicket(departmentId: $this->departmentId);

        $this->move($ticket, $this->otherDepartment)->assertOk();

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->getKey(),
            'event_type' => TicketEvent::DEPARTMENT_CHANGED,
        ]);
    }

    public function test_a_department_that_does_not_exist_is_refused(): void
    {
        $this->actingAs($this->supervisor());
        $ticket = $this->makeTicket(departmentId: $this->departmentId);

        $this->move($ticket, 999999)->assertStatus(422)
            ->assertJsonPath('code', 'tickets.department_invalid');

        $this->assertSame($this->departmentId, (int) $ticket->refresh()->department_id);
    }

    public function test_a_move_that_strands_the_assignee_clears_them(): void
    {
        $supervisor = $this->supervisor();
        $agent = $this->agent($this->departmentId);

        $ticket = $this->makeTicket(departmentId: $this->departmentId);
        $ticket->forceFill(['assignee_id' => $agent->getKey()])->save();

        $this->actingAs($supervisor);
        $this->move($ticket->refresh(), $this->otherDepartment)->assertOk();

        /*
         * Cleared rather than refused: the move is what somebody asked for and
         * is usually right, while the assignment is a detail that quietly
         * stopped making sense. Refusing would make them undo the assignment
         * first and then move — two steps for one intention.
         */
        $this->assertNull($ticket->refresh()->assignee_id);
    }

    public function test_clearing_the_stranded_assignee_gets_its_own_entry(): void
    {
        $supervisor = $this->supervisor();
        $agent = $this->agent($this->departmentId);

        $ticket = $this->makeTicket(departmentId: $this->departmentId);
        $ticket->forceFill(['assignee_id' => $agent->getKey()])->save();

        $this->actingAs($supervisor);
        $this->move($ticket->refresh(), $this->otherDepartment)->assertOk();

        $unassigned = TicketEvent::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', TicketEvent::ASSIGNEE_CHANGED)
            ->latest('created_at')
            ->firstOrFail();

        // "Why is this unassigned?" has an answer. Under `meta`, which is
        // where every event puts the detail specific to its own kind.
        $this->assertSame('department_changed', $unassigned->payload['meta']['reason']);
        $this->assertSame(
            $agent->getKey(),
            $unassigned->payload['meta']['previous_assignee_id'],
        );

        // And the change itself is still readable from the same two keys as
        // every other event.
        $this->assertSame($agent->getKey(), $unassigned->payload['before']['assignee_id']);
        $this->assertNull($unassigned->payload['after']['assignee_id']);
    }

    public function test_an_assignee_who_is_still_in_range_is_left_alone(): void
    {
        $supervisor = $this->supervisor();
        // No department of their own, so no move can strand them.
        $floating = $this->agent();

        $ticket = $this->makeTicket(departmentId: $this->departmentId);
        $ticket->forceFill(['assignee_id' => $floating->getKey()])->save();

        $this->actingAs($supervisor);
        $this->move($ticket->refresh(), $this->otherDepartment)->assertOk();

        $this->assertSame($floating->getKey(), $ticket->refresh()->assignee_id);
    }

    public function test_the_move_is_version_guarded(): void
    {
        $this->actingAs($this->supervisor());
        $ticket = $this->makeTicket(departmentId: $this->departmentId);

        $this->withIdempotencyKey()->patchJson("/api/v1/tickets/{$ticket->getKey()}/department", [
            'version' => 999,
            'department_id' => $this->otherDepartment,
        ])->assertStatus(409)->assertJsonPath('code', 'tickets.stale_version');
    }
}
