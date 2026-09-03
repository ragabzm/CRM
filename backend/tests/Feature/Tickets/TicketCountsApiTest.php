<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_the_sla_counters_say_unknown_rather_than_zero(): void
    {
        $response = $this->counts()->assertOk();

        /*
         * Null, not 0. There is no sla_state column yet, so "no ticket is at
         * risk" is a claim this system is in no position to make — and an agent
         * who read a confident zero would stop looking.
         */
        $this->assertNull($response->json('at_risk'));
        $this->assertNull($response->json('breached'));
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

    public function test_it_answers_in_one_query(): void
    {
        $this->makeTicket(['assignee_id' => $this->agent->getKey(), 'status' => 'open']);

        // Warm anything the framework caches on a first hit.
        $this->counts()->assertOk();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->counts()->assertOk();

        /*
         * Five round trips would be five times the load for a strip of numbers
         * that refreshes every thirty seconds — and the five would be taken at
         * five slightly different moments, so they could disagree with each
         * other and with the list they link to.
         */
        $onTickets = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'from "tickets"'),
        ));

        $this->assertCount(1, $onTickets, 'Counts must be one query: '.implode(' | ', $queries));
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
