<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Sla\Domain\NullSlaReader;
use App\Modules\Tickets\Contracts\SlaReader;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A count tile links to the tickets it counted.
 *
 * "At risk" and "Breached" on Home both built the same URL — every live
 * ticket, with no SLA condition at all — because the list had no way to
 * express one. Clicking a figure of 3 showed a page of 40, and the number and
 * the page it opened were unrelated.
 *
 * The list could not express it because SLA state is not a column: it is
 * computed from each ticket's timeline. So the filter asks the Sla module
 * which tickets are in the state and constrains on those ids.
 */
final class TicketListSlaFilterTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->agent = User::factory()->create();
        $this->agent->assignRole(Roles::SUPERVISOR);

        $this->actingAs($this->agent);
    }

    public function test_it_returns_only_the_tickets_in_that_state(): void
    {
        $fresh = $this->makeTicket(['status' => 'open']);
        $old = $this->makeTicket(['status' => 'open']);

        // Old enough that every target has passed.
        DB::table('tickets')->where('id', $old->getKey())->update(['created_at' => now()->subMonths(2)]);

        $ids = array_column(
            $this->getJson('/api/v1/tickets?sla_state=breached&per_page=50')->assertOk()->json('data'),
            'id',
        );

        $this->assertContains((string) $old->getKey(), $ids);
        $this->assertNotContains((string) $fresh->getKey(), $ids);
    }

    public function test_the_number_on_the_tile_matches_the_page_it_opens(): void
    {
        $this->makeTicket(['status' => 'open']);

        $old = $this->makeTicket(['status' => 'open']);
        DB::table('tickets')->where('id', $old->getKey())->update(['created_at' => now()->subMonths(2)]);

        $breached = $this->getJson('/api/v1/tickets/counts')->assertOk()->json('breached');

        $listed = $this->getJson('/api/v1/tickets?sla_state=breached&per_page=50')
            ->assertOk()
            ->json('meta.total');

        /*
         * The load-bearing property of the whole strip. A figure that does not
         * match the page behind it teaches an agent to distrust both.
         */
        $this->assertSame($breached, $listed);
    }

    public function test_an_invented_state_is_refused_rather_than_ignored(): void
    {
        // Silently ignoring it would show the unfiltered queue under a URL
        // that claims to be filtered.
        $this->getJson('/api/v1/tickets?sla_state=nearly')->assertStatus(422);
    }

    public function test_with_the_engine_off_it_shows_the_queue_rather_than_nothing(): void
    {
        $this->app->instance(SlaReader::class, new NullSlaReader);

        $this->makeTicket(['status' => 'open']);

        /*
         * Nothing knows the answer, so an empty page would be an assertion —
         * "no ticket is at risk" — from a deployment in no position to make
         * one. Showing the queue unfiltered asserts nothing.
         */
        $this->getJson('/api/v1/tickets?sla_state=at_risk&per_page=50')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_filter_cannot_widen_what_an_agent_may_see(): void
    {
        $colleague = User::factory()->create();
        $colleague->assignRole(Roles::AGENT);

        $theirs = $this->makeTicket(['status' => 'open', 'assignee_id' => $colleague->getKey()]);
        DB::table('tickets')->where('id', $theirs->getKey())->update(['created_at' => now()->subMonths(2)]);

        $agent = User::factory()->create();
        $agent->assignRole(Roles::AGENT);

        $ids = array_column(
            $this->actingAs($agent)
                ->getJson('/api/v1/tickets?sla_state=breached&per_page=50')
                ->assertOk()
                ->json('data'),
            'id',
        );

        // Visibility is applied before the SLA narrowing, so the candidate set
        // an agent's filter can draw from is already only theirs.
        $this->assertNotContains((string) $theirs->getKey(), $ids);
    }
}
