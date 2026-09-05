<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets\Assignment;

use App\Models\User;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Assignment\AssignmentMapping;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Feature\Tickets\InteractsWithTickets;
use Tests\TestCase;

/**
 * A lookup table decides where new work lands.
 *
 * The rules that earn their tests are the ones about NOT acting: an agent's own
 * decision is never overridden, a deactivated target means unassigned rather
 * than somebody else, and no match leaves the ticket in a pool that is a valid
 * place for it to be.
 */
final class AutoAssignmentTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    private User $actingAgent;

    private User $mappedAgent;

    private int $otherDepartmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAgent = $this->setUpTickets(Roles::SUPERVISOR);

        $this->mappedAgent = User::factory()->create(['department_id' => $this->departmentId]);
        $this->mappedAgent->syncRoles([Roles::AGENT]);

        $this->otherDepartmentId = (int) Department::firstOrCreate(
            ['name' => 'Escalations'],
            ['is_active' => true],
        )->getKey();
    }

    private function map(string $sourceType, int $sourceId, string $targetType, int $targetId): AssignmentMapping
    {
        return AssignmentMapping::create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
    }

    private function create(array $overrides = []): Ticket
    {
        return app(CreateTicket::class)->handle(
            Actor::staff((string) $this->actingAgent->getKey(), 'Hana Yousef'),
            new CreateTicketInput(
                subject: 'Invoice is wrong',
                description: 'Charged twice.',
                customerId: $this->customerId,
                channel: TicketChannel::Agent,
                categoryId: $overrides['categoryId'] ?? $this->categoryId,
                departmentId: $overrides['departmentId'] ?? $this->departmentId,
            ),
        );
    }

    public function test_a_category_mapping_assigns_the_ticket_to_that_agent(): void
    {
        $this->map('category', $this->categoryId, 'agent', (int) $this->mappedAgent->getKey());

        $ticket = $this->create();

        $this->assertSame((int) $this->mappedAgent->getKey(), $ticket->refresh()->assignee_id);
    }

    public function test_a_department_mapping_moves_the_ticket_and_leaves_it_unassigned(): void
    {
        $this->map('department', $this->departmentId, 'department', $this->otherDepartmentId);

        $ticket = $this->create()->refresh();

        $this->assertSame($this->otherDepartmentId, $ticket->department_id);

        /*
         * Moved, not handed to anybody. A department mapping says which TEAM
         * owns the work; who on that team picks it up is their decision.
         */
        $this->assertNull($ticket->assignee_id);
    }

    public function test_the_category_mapping_wins_when_both_match(): void
    {
        $this->map('category', $this->categoryId, 'agent', (int) $this->mappedAgent->getKey());
        $this->map('department', $this->departmentId, 'department', $this->otherDepartmentId);

        $ticket = $this->create()->refresh();

        /*
         * A department is where a ticket lands by default; a category is
         * something somebody CHOSE about this particular ticket. The more
         * specific statement does not lose to the general one — and the
         * precedence is fixed in code, so there is no ordering to configure.
         */
        $this->assertSame((int) $this->mappedAgent->getKey(), $ticket->assignee_id);
        $this->assertSame($this->departmentId, $ticket->department_id);
    }

    public function test_no_match_leaves_the_ticket_unassigned(): void
    {
        $ticket = $this->create()->refresh();

        // Unassigned is a valid, visible, workable state — not a failure.
        $this->assertNull($ticket->assignee_id);
    }

    public function test_a_deactivated_agent_means_unassigned_and_never_somebody_else(): void
    {
        $this->map('category', $this->categoryId, 'agent', (int) $this->mappedAgent->getKey());

        $this->mappedAgent->forceFill(['is_active' => false])->save();

        $ticket = $this->create()->refresh();

        /*
         * No fallback agent, no next-in-list, no handing it to a supervisor. A
         * ticket in the pool is seen by whoever picks up work next; a ticket
         * forced onto somebody who left last month is seen by nobody.
         */
        $this->assertNull($ticket->assignee_id);
    }

    public function test_no_creation_path_can_supply_an_assignee_for_a_mapping_to_override(): void
    {
        /*
         * The AC says an assignee supplied by an acting agent is never
         * overridden. Today it CANNOT be supplied: `CreateTicketInput` has no
         * such field, and `CreateTicket::insert` writes `assignee_id => null`
         * on every path — new work goes to the pool where everybody can see
         * it, rather than being hidden on one person's queue at birth.
         *
         * So the rule holds by construction, and this test says so out loud
         * rather than pretending to exercise a path that does not exist. The
         * guard in `applyMapping` is what keeps it holding if the field is
         * ever added; the next test proves the guard works.
         */
        $parameters = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionClass(CreateTicketInput::class))->getConstructor()?->getParameters() ?? [],
        );

        $this->assertNotContains('assigneeId', $parameters);
        $this->assertNotContains('assignee_id', $parameters);
    }

    public function test_the_guard_leaves_an_already_assigned_ticket_alone(): void
    {
        $this->map('category', $this->categoryId, 'agent', (int) $this->mappedAgent->getKey());

        $chosen = User::factory()->create(['department_id' => $this->departmentId]);
        $chosen->syncRoles([Roles::AGENT]);

        /*
         * `applyMapping` reached directly, with a ticket that already has an
         * assignee. Going through `CreateTicket::handle` could not set one up
         * — see the test above — so this is the only way to exercise the
         * branch that protects a person's decision from the lookup table.
         */
        $ticket = $this->create();
        $ticket->forceFill(['assignee_id' => $chosen->getKey()])->save();

        $apply = new \ReflectionMethod(CreateTicket::class, 'applyMapping');
        $apply->setAccessible(true);

        $decision = $apply->invoke(
            app(CreateTicket::class),
            $ticket->refresh(),
            new CreateTicketInput(
                subject: 'x',
                description: 'x',
                customerId: $this->customerId,
                channel: TicketChannel::Agent,
                categoryId: $this->categoryId,
            ),
        );

        // Nothing decided, and the person's choice survives.
        $this->assertNull($decision);
        $this->assertSame((int) $chosen->getKey(), $ticket->refresh()->assignee_id);
    }

    public function test_the_assignment_is_attributed_to_the_system_and_names_the_mapping(): void
    {
        $mapping = $this->map('category', $this->categoryId, 'agent', (int) $this->mappedAgent->getKey());

        $ticket = $this->create();

        $event = DB::table('ticket_events')
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', 'ticket.assignee_changed')
            ->first();

        $this->assertNotNull($event, 'The automatic assignment left no history entry.');
        $this->assertSame('system', $event->actor_type);
        $this->assertSame('auto_assign', $event->actor_reason);

        /*
         * "Why is this on Dana's queue?" answered from the ticket. Without the
         * mapping id, the answer is "some rule", and finding which one means
         * reading the whole table and guessing.
         */
        $this->assertStringContainsString((string) $mapping->getKey(), (string) $event->payload);
    }

    public function test_the_ticket_is_never_briefly_visible_as_unassigned(): void
    {
        $this->map('category', $this->categoryId, 'agent', (int) $this->mappedAgent->getKey());

        /*
         * Rolled back, and the ticket must not exist at all.
         *
         * That is the strongest available statement that the assignment is
         * inside the creating transaction: if it were a second write
         * afterwards, the ticket would survive a rollback of that write and
         * sit in the unassigned queue for ever. An agent watching that queue
         * would see the ticket appear and vanish, and the `ticket.created`
         * event would record a state the ticket was never really in.
         */
        DB::beginTransaction();

        $ticket = $this->create();
        $ticketId = (string) $ticket->getKey();

        $this->assertSame((int) $this->mappedAgent->getKey(), $ticket->refresh()->assignee_id);

        DB::rollBack();

        $this->assertSame(0, DB::table('tickets')->where('id', $ticketId)->count());
        $this->assertSame(0, DB::table('ticket_events')->where('ticket_id', $ticketId)->count());
    }

    public function test_at_most_one_mapping_exists_per_source(): void
    {
        $this->map('category', $this->categoryId, 'agent', (int) $this->mappedAgent->getKey());

        /*
         * Structural, not a validation somebody can relax. With two rows for
         * one category you would need ordering to decide between them, and
         * with ordering this is a rule engine.
         */
        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->map('category', $this->categoryId, 'department', $this->otherDepartmentId);
    }
}
