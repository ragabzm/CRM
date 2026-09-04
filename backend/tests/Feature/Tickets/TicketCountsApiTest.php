<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Sla\Domain\NullSlaReader;
use App\Modules\Tickets\Contracts\SlaReader;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The five numbers an agent sees before anything else.
 */
final class TicketCountsApiTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->agent = $this->makeUser(Roles::AGENT);
    }

    private function counts(?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->agent)->getJson('/api/v1/tickets/counts');
    }

    public function test_it_returns_every_counter_even_before_the_sla_module_exists(): void
    {
        $response = $this->counts()->assertOk();

        // The keys are present so the strip's shape does not change when Story
        // 5.3 lands and fills the last two in.
        foreach ([
            'assigned_to_me',
            'unassigned',
            'at_risk',
            'breached',
            'pending_customer_reply',
        ] as $key) {
            $this->assertArrayHasKey($key, $response->json());
        }
    }

    public function test_the_sla_counters_say_unknown_when_nothing_is_tracking(): void
    {
        /*
         * Null, not 0, when the engine is OFF. "No ticket is at risk" is a
         * claim a deployment with no SLA module is in no position to make, and
         * an agent who read a confident zero would stop looking.
         *
         * This used to be the answer ALWAYS, with a comment saying the SLA
         * module did not exist yet. It shipped; the comment did not move; and
         * the two tiles on Home read "Not tracked yet" for three stories on a
         * screen that was showing live SLA badges in the list underneath.
         */
        $this->app->instance(SlaReader::class, new NullSlaReader);

        $response = $this->counts()->assertOk();

        $this->assertNull($response->json('at_risk'));
        $this->assertNull($response->json('breached'));
    }

    public function test_the_sla_counters_count_when_something_is_tracking(): void
    {
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'open']);

        $response = $this->counts()->assertOk();

        // A number, not a dash. Zero here is a real answer: the engine looked.
        $this->assertIsInt($response->json('at_risk'));
        $this->assertIsInt($response->json('breached'));
    }

    public function test_a_breached_ticket_is_counted_as_breached(): void
    {
        $ticket = $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'open']);

        /*
         * Old enough that any target has passed. The point is not the exact
         * arithmetic — that is the SLA module's own tests — it is that the
         * tile and the badge come from the same reading, so they cannot
         * disagree on screen.
         */
        DB::table('tickets')
            ->where('id', $ticket->getKey())
            ->update(['created_at' => now()->subMonths(2)]);

        $counts = $this->counts()->assertOk();

        $badge = $this->getJson('/api/v1/tickets?per_page=50')
            ->assertOk()
            ->json('data.0.sla.state');

        $this->assertSame('breached', $badge);
        $this->assertSame(1, $counts->json('breached'));
    }

    public function test_it_counts_what_is_assigned_to_the_caller(): void
    {
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'open']);
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'pending']);
        // Finished work is not on anyone's plate.
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'closed']);

        $this->counts()->assertOk()->assertJsonPath('assigned_to_me', 2);
    }

    public function test_it_counts_the_pool_nobody_has_picked_up(): void
    {
        $this->makeTicket(['assignee_id' => null, 'status' => 'open']);
        $this->makeTicket(['assignee_id' => null, 'status' => 'closed']);

        $this->counts()->assertOk()->assertJsonPath('unassigned', 1);
    }

    public function test_it_counts_what_is_waiting_on_the_customer(): void
    {
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'pending']);

        $this->counts()->assertOk()->assertJsonPath('pending_customer_reply', 1);
    }

    public function test_an_agent_counts_only_what_they_can_open(): void
    {
        $someone = $this->makeUser(Roles::AGENT);

        $this->makeTicket(['assignee_id' => $someone->getKey(), 'status' => 'open']);
        $this->makeTicket(['assignee_id' => null, 'status' => 'open']);

        /*
         * A count that included work they cannot open would be a number they
         * can never act on — and clicking it would land them on an empty list.
         */
        $response = $this->counts()->assertOk();

        $this->assertSame(0, $response->json('assigned_to_me'));
        $this->assertSame(1, $response->json('unassigned'));
    }

    public function test_a_supervisor_counts_the_whole_queue(): void
    {
        $someone = $this->makeUser(Roles::AGENT);

        $this->makeTicket(['assignee_id' => $someone->getKey(), 'status' => 'open']);
        $this->makeTicket(['assignee_id' => null, 'status' => 'open']);

        // Supervision that cannot see the queue is not supervision.
        $this->counts($this->makeUser(Roles::SUPERVISOR))
            ->assertOk()
            ->assertJsonPath('unassigned', 1);
    }

    public function test_the_five_numbers_come_from_one_pass(): void
    {
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'open']);

        // Warm anything the framework caches on a first hit.
        $this->counts()->assertOk();

        $queries = $this->queriesFor(fn () => $this->counts()->assertOk());

        /*
         * Five round trips for the five numbers would be five times the load
         * for a strip that refreshes every thirty seconds — and the five would
         * be taken at five slightly different moments, so they could disagree
         * with each other and with the list they link to.
         */
        $aggregates = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'assigned_to_me'),
        ));

        $this->assertCount(1, $aggregates, 'The five numbers must come from one pass: '.implode(' | ', $queries));
    }

    public function test_the_sla_tally_does_not_cost_a_query_per_ticket(): void
    {
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'open']);
        $this->counts()->assertOk();

        $withOne = count($this->queriesFor(fn () => $this->counts()->assertOk()));

        for ($i = 0; $i < 11; $i++) {
            $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'open']);
        }

        $withTwelve = count($this->queriesFor(fn () => $this->counts()->assertOk()));

        /*
         * This is the property that actually matters, and a fixed number would
         * not have caught it.
         *
         * The SLA tiles cannot be aggregated in SQL — state is computed from
         * each ticket's timeline rather than stored — so the tally reads the
         * open queue. Reading it ONE TICKET AT A TIME would look identical on
         * a developer's four rows and fall over on a real desk's four hundred.
         */
        $this->assertSame(
            $withOne,
            $withTwelve,
            'The number of queries grew with the number of tickets.',
        );
    }

    /**
     * @param  callable(): mixed  $work
     * @return list<string>
     */
    private function queriesFor(callable $work): array
    {
        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, '"tickets"') || str_contains($query->sql, 'ticket_')) {
                $queries[] = $query->sql;
            }
        });

        $work();

        return $queries;
    }

    public function test_the_counts_agree_with_the_list_they_link_to(): void
    {
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'open']);
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'pending']);
        $this->makeTicket(['assignee_id' => null, 'status' => 'open']);

        $counts = $this->counts()->assertOk();

        /*
         * The load-bearing property of the whole strip: a figure that does not
         * reproduce the list behind it is worse than no figure, because the
         * agent trusts it and then finds a different number of rows.
         */
        $listed = $this->actingAs($this->agent)
            ->getJson('/api/v1/tickets?assignee_id='.$this->agent->getKey().'&status=open,pending')
            ->assertOk();

        $this->assertSame($counts->json('assigned_to_me'), $listed->json('meta.total'));

        $pool = $this->actingAs($this->agent)
            ->getJson('/api/v1/tickets?assignee_id=unassigned&status=open,pending')
            ->assertOk();

        $this->assertSame($counts->json('unassigned'), $pool->json('meta.total'));
    }

    public function test_it_refuses_an_unauthenticated_caller(): void
    {
        $this->getJson('/api/v1/tickets/counts')->assertStatus(401);
    }
}
