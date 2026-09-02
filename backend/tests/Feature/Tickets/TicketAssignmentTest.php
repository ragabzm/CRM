<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Department;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Whose work you may take.
 *
 * The interesting rule is not "who may assign" — most staff may — but the
 * difference between picking up unclaimed work and pulling a ticket out of a
 * colleague's hands. The second needs a supervisor, because the person holding
 * it may be three messages into a thread nobody else can see.
 */
final class TicketAssignmentTest extends TestCase
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

    private function assign(Ticket $ticket, ?int $assigneeId): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$ticket->getKey()}/assign", [
            'version' => (int) $ticket->refresh()->version,
            'assignee_id' => $assigneeId,
        ]);
    }

    public function test_an_agent_takes_an_unassigned_ticket(): void
    {
        $agent = $this->agent();
        $ticket = $this->makeTicket();

        $this->actingAs($agent);

        // Picking up unclaimed work is most of an agent's day.
        $this->assign($ticket, (int) $agent->getKey())
            ->assertOk()
            ->assertJsonPath('assignee_id', $agent->getKey());
    }

    public function test_an_agent_cannot_take_a_ticket_a_colleague_holds(): void
    {
        $colleague = $this->agent();
        $agent = $this->agent();
        $ticket = $this->makeTicketHeldBy($colleague);

        $this->actingAs($agent);

        $response = $this->assign($ticket, (int) $agent->getKey())->assertStatus(403);

        $response->assertJsonPath('code', 'tickets.reassign_forbidden');
        // The ticket did not move.
        $this->assertSame($colleague->getKey(), Ticket::query()->findOrFail($ticket->getKey())->assignee_id);
    }

    public function test_a_supervisor_reassigns_any_ticket(): void
    {
        $colleague = $this->agent();
        $supervisor = $this->supervisor();
        $ticket = $this->makeTicketHeldBy($colleague);

        $this->actingAs($supervisor);

        // Supervision that cannot move work is not supervision.
        $this->assign($ticket, (int) $supervisor->getKey())
            ->assertOk()
            ->assertJsonPath('assignee_id', $supervisor->getKey());
    }

    public function test_an_agent_may_hand_back_a_ticket_they_hold(): void
    {
        $agent = $this->agent();
        $ticket = $this->makeTicketHeldBy($agent);

        $this->actingAs($agent);

        // Returning your own work to the pool is not a reassignment.
        $this->assign($ticket, null)->assertOk()->assertJsonPath('assignee_id', null);
    }

    public function test_an_agent_cannot_unassign_a_colleagues_ticket(): void
    {
        $colleague = $this->agent();
        $agent = $this->agent();
        $ticket = $this->makeTicketHeldBy($colleague);

        $this->actingAs($agent);

        // Same rule as taking it: clearing the assignee still removes it from
        // the person who was working it.
        $this->assign($ticket, null)->assertStatus(403)
            ->assertJsonPath('code', 'tickets.reassign_forbidden');
    }

    public function test_a_deactivated_person_cannot_be_given_a_ticket(): void
    {
        $supervisor = $this->supervisor();
        $gone = $this->agent();
        $gone->forceFill(['is_active' => false])->save();

        $ticket = $this->makeTicket();
        $this->actingAs($supervisor);

        // Assigning to a deactivated account parks the work somewhere nobody
        // is looking.
        $this->assign($ticket, (int) $gone->getKey())->assertStatus(422)
            ->assertJsonPath('code', 'tickets.assignee_invalid');
    }

    public function test_someone_from_another_department_cannot_be_given_the_ticket(): void
    {
        $other = (int) Department::create(['name' => 'Support', 'is_active' => true])->getKey();

        $supervisor = $this->supervisor();
        $outsider = $this->agent($other);
        $ticket = $this->makeTicket(departmentId: $this->departmentId);

        $this->actingAs($supervisor);

        $response = $this->assign($ticket, (int) $outsider->getKey())->assertStatus(422);

        $response->assertJsonPath('code', 'tickets.assignee_invalid');
        // The refusal says what to do instead.
        $this->assertStringContainsString('Move the ticket', (string) $response->json('detail'));
    }

    public function test_someone_in_no_department_can_be_given_any_ticket(): void
    {
        $supervisor = $this->supervisor();
        $floating = $this->agent();
        $ticket = $this->makeTicket(departmentId: $this->departmentId);

        $this->actingAs($supervisor);

        // Not in a department is not the same as excluded from one.
        $this->assign($ticket, (int) $floating->getKey())->assertOk();
    }

    public function test_every_assignment_is_recorded(): void
    {
        $agent = $this->agent();
        $ticket = $this->makeTicket();

        $this->actingAs($agent);
        $this->assign($ticket, (int) $agent->getKey())->assertOk();

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->getKey(),
            'event_type' => TicketEvent::ASSIGNEE_CHANGED,
        ]);
    }

    public function test_assignment_is_version_guarded(): void
    {
        $agent = $this->agent();
        $ticket = $this->makeTicket();

        $this->actingAs($agent);

        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$ticket->getKey()}/assign", [
            'version' => 999,
            'assignee_id' => $agent->getKey(),
        ])->assertStatus(409)->assertJsonPath('code', 'tickets.stale_version');
    }

    public function test_a_guest_cannot_assign(): void
    {
        $ticket = $this->makeTicket();
        $agent = $this->agent();

        $this->app['auth']->forgetGuards();
        $this->refreshApplication();
        $this->setUpSpaOrigin();

        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$ticket->getKey()}/assign", [
            'version' => 1,
            'assignee_id' => $agent->getKey(),
        ])->assertStatus(401);
    }
}
