<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Who this ticket is for, in one query.
 *
 * The panel is only worth having if it costs nothing. A context panel that
 * adds a second to every ticket gets collapsed, and then the agent stops
 * knowing this is the customer's fourth request this month — which was the
 * whole reason for showing it.
 */
final class CustomerContextAggregateTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private string $customerId;

    private string $ticketId;

    /** Created once: making a user per call would count as the endpoint's queries. */
    private \App\Models\User $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->customerId = $this->makeCustomer();
        $this->reader = $this->makeUser(Roles::ADMINISTRATOR);

        // A busy customer, which is exactly when an N+1 would show.
        foreach (['open', 'open', 'pending', 'resolved', 'closed'] as $status) {
            $ticket = $this->makeTicket(['customer_id' => $this->customerId, 'status' => $status]);
            $this->ticketId ??= (string) $ticket->getKey();
        }

        for ($i = 0; $i < 15; $i++) {
            $this->makeTicket(['customer_id' => $this->customerId, 'status' => 'closed']);
        }
    }

    private function context(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->reader)
            ->getJson("/api/v1/tickets/{$this->ticketId}/customer-context");
    }

    public function test_it_counts_only_the_tickets_that_still_need_attention(): void
    {
        // Two open plus one pending. Counting resolved and closed would tell an
        // agent this person has twenty live problems when they have three.
        $this->context()->assertOk()->assertJsonPath('open_ticket_count', 3);
    }

    public function test_it_counts_everything_raised_recently(): void
    {
        // All twenty were created just now, whatever their status: "recent"
        // answers "is this person having a bad month", not "what is open".
        $this->context()->assertOk()->assertJsonPath('recent_ticket_count', 20);
    }

    public function test_it_names_the_window_so_the_number_can_be_read(): void
    {
        // "4 recent" means nothing without "recent since when".
        $this->context()->assertOk()->assertJsonPath('recent_window_days', 30);
    }

    public function test_it_reports_the_last_time_anyone_actually_spoke(): void
    {
        $this->context()->assertOk()->assertJsonPath('last_interaction_at', null);

        $agent = $this->makeUser(Roles::AGENT);
        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            $this->ticketId,
            MessageDirection::Inbound,
            'Still waiting.',
        );

        // A message, not a row's updated_at — a background sweep touching rows
        // would otherwise keep claiming the customer just got in touch.
        $this->assertNotNull($this->context()->assertOk()->json('last_interaction_at'));
    }

    public function test_it_carries_the_contact_and_department(): void
    {
        $response = $this->context()->assertOk();

        $response->assertJsonPath('full_name', 'Someone');
        $this->assertNotNull($response->json('reference'));
        $this->assertSame('Billing', $response->json('department.name'));
    }

    public function test_it_answers_in_one_query(): void
    {
        // Warm anything the framework caches on a first hit, so the count below
        // is about this endpoint rather than about booting it.
        $this->context()->assertOk();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->context()->assertOk();

        /*
         * One for the visibility check, one for the aggregate. Counts as
         * correlated subqueries rather than three round trips, and no relation
         * loading — which is where the N+1 would come back the first time
         * somebody added a field.
         */
        $aggregates = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'from "customers"'),
        ));

        $this->assertCount(1, $aggregates, 'The context must be one query: '.implode(' | ', $queries));
        $this->assertLessThanOrEqual(2, count($queries), implode(' | ', $queries));
    }

    public function test_an_agent_who_cannot_see_the_ticket_learns_nothing(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket([
            'customer_id' => $this->customerId,
            'assignee_id' => $owner->getKey(),
        ]);

        /*
         * 404, and no customer name in the body. Without the visibility check
         * this endpoint would be a way to learn a customer's identity and
         * volume from a ticket id alone.
         */
        $response = $this->actingAs($this->makeUser(Roles::AGENT))
            ->getJson("/api/v1/tickets/{$ticket->getKey()}/customer-context");

        $response->assertStatus(404);
        $this->assertStringNotContainsString('Someone', (string) $response->getContent());
    }
}
