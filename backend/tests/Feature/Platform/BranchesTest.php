<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Branches\Domain\Branch;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A branch is a label and a filter, and never an access boundary.
 *
 * That last clause is the whole story. Every requirement here is easy; the one
 * that matters is the ABSENCE — no global scope, no narrowed read, no narrowed
 * write. A request that returns a ticket from another branch is correct
 * behaviour, and there is a test below that asserts exactly that rather than
 * leaving it as a note in a file.
 *
 * The failure this prevents is not a leak. It is the opposite: a scope
 * registered "just for filtering", and then a supervisor asking why a ticket
 * they were handed has disappeared — with nothing in the logs, because a
 * global scope leaves none.
 */
final class BranchesTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->administrator = $this->makeUser(Roles::ADMINISTRATOR);
    }

    private function branch(string $name = 'Cairo', string $code = 'CAI'): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['name' => $name, 'code' => $code, 'is_active' => true])->save();

        return $branch;
    }

    public function test_an_administrator_creates_a_branch(): void
    {
        $response = $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/branches', ['name' => 'Cairo', 'code' => 'cai']);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Cairo');
        // Upper-cased, so `CAI` and `cai` cannot become two branches that read
        // as one to every person and as two to the database.
        $response->assertJsonPath('data.code', 'CAI');
        $response->assertJsonPath('data.is_active', true);
    }

    public function test_two_branches_cannot_share_a_code(): void
    {
        $this->branch();

        $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/branches', ['name' => 'Cairo West', 'code' => 'CAI'])
            ->assertStatus(422);
    }

    public function test_deactivation_never_deletes_and_never_orphans(): void
    {
        $branch = $this->branch();
        $ticket = $this->makeTicket();
        $ticket->forceFill(['branch_id' => $branch->getKey()])->save();

        $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->patchJson('/api/v1/branches/'.$branch->getKey(), ['is_active' => false])
            ->assertOk();

        /*
         * The row survives, and so does every record pointing at it. A closed
         * branch still describes where three years of tickets happened —
         * deleting it would either take them with it or leave them pointing
         * at nothing.
         */
        $this->assertSame(1, Branch::query()->count());
        $this->assertFalse((bool) $branch->refresh()->is_active);
        $this->assertSame((int) $branch->getKey(), (int) $ticket->refresh()->branch_id);
    }

    public function test_there_is_no_way_to_delete_one(): void
    {
        $branch = $this->branch();

        $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->deleteJson('/api/v1/branches/'.$branch->getKey())
            ->assertStatus(405);
    }

    public function test_a_change_is_recorded_with_before_and_after(): void
    {
        $branch = $this->branch();

        $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->patchJson('/api/v1/branches/'.$branch->getKey(), ['name' => 'Cairo Central']);

        $entry = DB::table('audit_entries')->where('action', 'branch.updated')->first();

        $this->assertNotNull($entry);

        $before = json_decode((string) $entry->before, true);
        $after = json_decode((string) $entry->after, true);

        /*
         * "The Cairo branch was renamed" is not an answer to "renamed from
         * what?", and that second question is the one asked six months later.
         */
        $this->assertSame('Cairo', $before['name']);
        $this->assertSame('Cairo Central', $after['name']);
    }

    public function test_a_ticket_inherits_its_customers_branch(): void
    {
        $branch = $this->branch();
        $customerId = $this->makeCustomer();

        DB::table('customers')->where('id', $customerId)->update(['branch_id' => $branch->getKey()]);

        $agent = $this->makeUser(Roles::AGENT);

        $ticket = $this->app->make(CreateTicket::class)->handle(
            Actor::staff((string) $agent->getKey(), (string) $agent->name),
            new CreateTicketInput(
                subject: 'A request',
                description: 'A body',
                customerId: $customerId,
                channel: TicketChannel::Agent,
            ),
        );

        $this->assertSame((int) $branch->getKey(), (int) $ticket->branch_id);
    }

    public function test_a_customer_with_no_branch_produces_a_ticket_with_none(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $ticket = $this->app->make(CreateTicket::class)->handle(
            Actor::staff((string) $agent->getKey(), (string) $agent->name),
            new CreateTicketInput(
                subject: 'A request',
                description: 'A body',
                customerId: $this->makeCustomer(),
                channel: TicketChannel::Agent,
            ),
        );

        /*
         * And nothing waits for it. An unresolved branch has NO consequence —
         * nothing is refused, nothing is narrowed, nothing falls back — which
         * is exactly why branch needs no resolution order of its own, unlike
         * department.
         */
        $this->assertNull($ticket->branch_id);
    }

    public function test_branch_narrows_nothing_unless_the_reader_asks(): void
    {
        $cairo = $this->branch('Cairo', 'CAI');
        $alex = $this->branch('Alexandria', 'ALX');

        $mine = $this->makeTicket();
        $mine->forceFill(['branch_id' => $cairo->getKey()])->save();

        $theirs = $this->makeTicket();
        $theirs->forceFill(['branch_id' => $alex->getKey()])->save();

        $agent = $this->makeUser(Roles::AGENT);
        $agent->forceFill(['branch_id' => $cairo->getKey()])->save();

        $list = $this->actingAs($agent->refresh(), 'web')->getJson('/api/v1/tickets');

        /*
         * THE ASSERTION THIS STORY EXISTS FOR.
         *
         * An agent in Cairo sees the Alexandria ticket. That is correct
         * behaviour, not a leak: branch is a label, there is no branch-scoped
         * access in this product, and a request that quietly returned fewer
         * rows would be a permission model nobody designed, nobody documented
         * and nobody can debug.
         */
        $list->assertOk();
        $this->assertSame(2, count($list->json('data')));
    }

    public function test_the_reader_can_ask_for_one_branch(): void
    {
        $cairo = $this->branch('Cairo', 'CAI');
        $alex = $this->branch('Alexandria', 'ALX');

        $this->makeTicket()->forceFill(['branch_id' => $cairo->getKey()])->save();
        $this->makeTicket()->forceFill(['branch_id' => $alex->getKey()])->save();

        $agent = $this->makeUser(Roles::AGENT);

        $list = $this->actingAs($agent, 'web')
            ->getJson('/api/v1/tickets?branch_id='.$cairo->getKey());

        // A filter the user chooses, never one the system applies.
        $this->assertSame(1, count($list->json('data')));
    }

    public function test_no_global_scope_is_registered_for_branch(): void
    {
        /*
         * Checked on the model itself, because a scope is invisible at every
         * call site — a query that has been silently narrowed looks exactly
         * like a query with nothing to return.
         */
        foreach ([Ticket::class, Branch::class, User::class] as $model) {
            $scopes = (new $model)->getGlobalScopes();

            $this->assertSame(
                [],
                array_keys($scopes),
                $model.' has a global scope. Branch is a label, not an access boundary.',
            );
        }
    }

    public function test_an_agent_may_read_the_branches_but_not_change_them(): void
    {
        $this->branch();

        $agent = $this->makeUser(Roles::AGENT);

        // A label nobody can read is not a label.
        $this->actingAs($agent, 'web')->getJson('/api/v1/branches')->assertOk();

        // Administering the org chart is a different thing entirely.
        $this->actingAs($agent, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/branches', ['name' => 'Theirs', 'code' => 'THR'])
            ->assertForbidden();
    }
}
