<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Personal\Task;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The things an agent writes down for themselves.
 *
 * A task is PERSONAL work. The rules worth the most attention are the ones
 * about what it cannot do: it cannot be handed to a colleague, it cannot move
 * a ticket, and it cannot be read by anybody else — three properties that stop
 * a to-do list becoming a second, quieter assignment queue running beside the
 * real one.
 */
final class PersonalWorkTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->agent = $this->makeUser(Roles::AGENT);
    }

    private function asAgent(?User $user = null): self
    {
        // The guard is named. Without it a portal sign-in earlier in the same
        // test would decide which guard this lands on.
        $this->actingAs($user ?? $this->agent, 'web');

        return $this;
    }

    public function test_an_agent_writes_a_standalone_task(): void
    {
        $response = $this->asAgent()
            ->withIdempotencyKey()
            ->postJson('/api/v1/me/tasks', ['title' => 'Call Najd Logistics']);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Call Najd Logistics');
        // The null ticket IS the standalone feature — no second table for it.
        $response->assertJsonPath('data.ticket_id', null);
        $response->assertJsonPath('data.completed_at', null);
    }

    public function test_a_task_can_be_attached_to_a_ticket_and_carries_its_reference(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->asAgent()
            ->withIdempotencyKey()
            ->postJson('/api/v1/me/tasks', [
                'title' => 'Chase the courier hub',
                'ticket_id' => (string) $ticket->getKey(),
            ]);

        $response->assertCreated();
        /*
         * The reference and subject travel with the row, so the agent knows
         * what they are about to open without a second request per task.
         */
        $response->assertJsonPath('data.ticket_reference', $ticket->reference);
        $response->assertJsonPath('data.ticket_subject', $ticket->subject);
    }

    public function test_a_task_has_no_description_no_assignee_and_no_parent(): void
    {
        $response = $this->asAgent()
            ->withIdempotencyKey()
            ->postJson('/api/v1/me/tasks', [
                'title' => 'A task',
                // Each of these is the first half of a project tracker.
                'description' => 'Some detail',
                'assignee_id' => 999,
                'parent_id' => 'something',
                'recurrence' => 'weekly',
            ]);

        $response->assertCreated();

        $columns = array_keys((array) DB::table('tasks')->first());

        foreach (['description', 'assignee_id', 'parent_id', 'recurrence'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns, "`{$forbidden}` has appeared on a task.");
        }
    }

    public function test_a_task_cannot_be_created_for_somebody_else(): void
    {
        $colleague = $this->makeUser(Roles::AGENT);

        $this->asAgent()
            ->withIdempotencyKey()
            ->postJson('/api/v1/me/tasks', [
                'title' => 'Not yours to give',
                'user_id' => (int) $colleague->getKey(),
            ])->assertCreated();

        /*
         * The owner is read off the session, never off the body. A task that
         * could be created for a colleague is an assignment, and assignment
         * already exists on the ticket.
         */
        $this->assertSame(
            (int) $this->agent->getKey(),
            (int) DB::table('tasks')->value('user_id'),
        );
    }

    public function test_a_blank_title_is_refused_with_the_reason(): void
    {
        $response = $this->asAgent()
            ->withIdempotencyKey()
            ->postJson('/api/v1/me/tasks', ['title' => '   ']);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('detail') ?? $response->json('message'));
    }

    public function test_completing_a_task_is_a_state_and_can_be_undone(): void
    {
        $task = $this->task();

        $this->asAgent()
            ->withIdempotencyKey()
            ->patchJson('/api/v1/me/tasks/'.$task->getKey(), ['completed' => true])
            ->assertOk();

        $this->assertNotNull($task->refresh()->completed_at);
        // Not a delete: the row is the evidence the work happened.
        $this->assertSame(1, Task::query()->count());

        $this->asAgent()
            ->withIdempotencyKey()
            ->patchJson('/api/v1/me/tasks/'.$task->getKey(), ['completed' => false])
            ->assertOk();

        $this->assertNull($task->refresh()->completed_at);
    }

    public function test_ticking_a_ticked_task_does_not_move_its_completion_time(): void
    {
        $task = $this->task();

        $this->asAgent()->withIdempotencyKey()
            ->patchJson('/api/v1/me/tasks/'.$task->getKey(), ['completed' => true]);

        $first = $task->refresh()->completed_at;

        $this->travel(1)->hour();

        $this->asAgent()->withIdempotencyKey()
            ->patchJson('/api/v1/me/tasks/'.$task->getKey(), ['completed' => true]);

        $this->assertEquals($first, $task->refresh()->completed_at);
    }

    public function test_a_colleagues_task_is_not_found_rather_than_forbidden(): void
    {
        $colleague = $this->makeUser(Roles::AGENT);
        $theirs = $this->task($colleague);

        /*
         * 404, not 403. A 403 confirms the id exists, and these ids are the
         * only thing separating one agent's private list from another's.
         */
        $this->asAgent()
            ->withIdempotencyKey()
            ->patchJson('/api/v1/me/tasks/'.$theirs->getKey(), ['completed' => true])
            ->assertNotFound();

        $this->assertNull($theirs->refresh()->completed_at);
    }

    public function test_the_list_shows_only_my_own_tasks(): void
    {
        $colleague = $this->makeUser(Roles::AGENT);
        $this->task($colleague, 'Theirs');
        $this->task($this->agent, 'Mine');

        $response = $this->asAgent()->getJson('/api/v1/me/tasks');

        $response->assertOk();
        $this->assertSame(['Mine'], array_column($response->json('data'), 'title'));
    }

    public function test_an_overdue_task_is_marked_as_such_by_the_server(): void
    {
        $overdue = $this->task(title: 'Late');
        $overdue->forceFill(['due_at' => now()->subDay()])->save();

        $soon = $this->task(title: 'Later');
        $soon->forceFill(['due_at' => now()->addDay()])->save();

        $rows = collect($this->asAgent()->getJson('/api/v1/me/tasks')->json('data'))
            ->keyBy('title');

        /*
         * Decided on the SERVER. Overdue computed in the browser is decided
         * against the reader's own clock, which is the one place it can
         * silently be wrong — and it is the cue the whole row is built around.
         */
        $this->assertTrue($rows['Late']['overdue']);
        $this->assertFalse($rows['Later']['overdue']);
    }

    public function test_a_completed_task_is_never_overdue(): void
    {
        $task = $this->task();
        $task->forceFill(['due_at' => now()->subWeek(), 'completed_at' => now()])->save();

        $row = $this->asAgent()->getJson('/api/v1/me/tasks')->json('data.0');

        // Finishing late is not the same as still owing it.
        $this->assertFalse($row['overdue']);
    }

    public function test_open_tasks_sort_above_completed_ones_and_soonest_first(): void
    {
        $this->task(title: 'Done')->forceFill(['completed_at' => now()])->save();
        $this->task(title: 'Next week')->forceFill(['due_at' => now()->addWeek()])->save();
        $this->task(title: 'Tomorrow')->forceFill(['due_at' => now()->addDay()])->save();
        $this->task(title: 'No date');

        $titles = array_column($this->asAgent()->getJson('/api/v1/me/tasks')->json('data'), 'title');

        /*
         * Undated tasks sit below dated ones rather than at the top: a task
         * with no due date is not more urgent than one due in an hour, and
         * most databases sort null first if left to themselves.
         */
        $this->assertSame(['Tomorrow', 'Next week', 'No date', 'Done'], $titles);
    }

    public function test_a_task_never_touches_its_ticket(): void
    {
        $ticket = $this->makeTicket(['status' => 'open']);
        $before = $ticket->refresh()->only(['status', 'assignee_id', 'version', 'updated_at']);

        $created = $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/tasks', [
            'title' => 'Chase this',
            'ticket_id' => (string) $ticket->getKey(),
        ])->json('data.id');

        $this->asAgent()->withIdempotencyKey()
            ->patchJson('/api/v1/me/tasks/'.$created, ['completed' => true]);

        /*
         * An agent's private note to self is not a statement about where the
         * customer's request has got to. Wiring the two would let a personal
         * checklist quietly resolve people's tickets.
         */
        $this->assertEquals($before, $ticket->refresh()->only(['status', 'assignee_id', 'version', 'updated_at']));
        $this->assertSame(0, DB::table('ticket_events')->where('ticket_id', $ticket->getKey())->count());
    }

    public function test_a_task_on_a_missing_ticket_is_refused(): void
    {
        $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/tasks', [
            'title' => 'About nothing',
            'ticket_id' => (string) \Illuminate\Support\Str::ulid(),
        ])->assertNotFound();
    }

    public function test_tasks_are_invisible_on_every_customer_surface(): void
    {
        $ticket = $this->makeTicket();

        $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/tasks', [
            'title' => 'Do not show this to the customer',
            'ticket_id' => (string) $ticket->getKey(),
        ])->assertCreated();

        /*
         * Called directly, not read off a screen. The portal is a different
         * guard and a different gateway; the check that matters is that no
         * portal response can be made to contain this string.
         */
        $this->app['auth']->forgetGuards();

        $portal = $this->getJson('/api/v1/portal/requests');
        $portal->assertUnauthorized();

        $this->assertStringNotContainsString('Do not show this to the customer', $portal->getContent());
    }

    public function test_the_home_badge_counts_ride_with_the_counts_strip(): void
    {
        $this->task(title: 'Open one');
        $this->task(title: 'Overdue one')->forceFill(['due_at' => now()->subDay()])->save();
        $this->task(title: 'Finished')->forceFill(['completed_at' => now()])->save();

        $response = $this->asAgent()->getJson('/api/v1/tickets/counts');

        $response->assertOk();
        /*
         * In the SAME response as the strip. Home refreshes every thirty
         * seconds; a tab badge is not worth a round trip of its own, and two
         * requests taken moments apart can disagree on screen.
         */
        $response->assertJsonPath('personal.tasks', 2);
        $response->assertJsonPath('personal.tasks_overdue', 1);
        $response->assertJsonPath('personal.mentions', 0);
    }

    public function test_signing_out_leaves_nothing_reachable(): void
    {
        $this->getJson('/api/v1/me/tasks')->assertUnauthorized();
    }

    private function task(?User $owner = null, string $title = 'A task'): Task
    {
        $task = new Task;

        $task->forceFill([
            'user_id' => (int) ($owner ?? $this->agent)->getKey(),
            'title' => $title,
        ])->save();

        return $task;
    }
}
