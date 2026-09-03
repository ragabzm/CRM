<?php

declare(strict_types=1);

namespace Tests\Feature\Sla;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * What the SLA engine puts on screen.
 */
final class SlaApiTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $s = $this->app->make(SettingsRegistry::class);
        $s->set('email.enabled', false, null);
        $s->set('sla.timezone', 'Asia/Riyadh', null);
        $s->set('sla.response_target_seconds.normal', 3600, null);
        $s->set('sla.resolution_target_seconds.normal', 14400, null);
    }

    private function supervisor(): \App\Models\User
    {
        return $this->makeUser(Roles::SUPERVISOR);
    }

    public function test_a_ticket_carries_both_timers(): void
    {
        $ticket = $this->makeTicket(['priority' => 'normal']);

        $sla = $this->actingAs($this->supervisor())
            ->getJson("/api/v1/tickets/{$ticket->getKey()}")
            ->assertOk()
            ->json('sla');

        $this->assertArrayHasKey('response', $sla);
        $this->assertArrayHasKey('resolution', $sla);
        $this->assertSame('on_track', $sla['response']['state']);
        $this->assertSame(60, $sla['response']['target_minutes']);
    }

    public function test_each_timer_says_how_long_is_left(): void
    {
        $ticket = $this->makeTicket(['priority' => 'normal']);

        $sla = $this->actingAs($this->supervisor())
            ->getJson("/api/v1/tickets/{$ticket->getKey()}")
            ->assertOk()
            ->json('sla');

        // Remaining, not just elapsed: "40 minutes left" is what an agent
        // decides on, and it is the number a countdown renders.
        $this->assertArrayHasKey('remaining_minutes', $sla['response']);
        $this->assertArrayHasKey('due_at', $sla['response']);
    }

    public function test_the_list_carries_the_same_block(): void
    {
        $this->makeTicket(['priority' => 'normal']);

        $row = $this->actingAs($this->supervisor())
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->json('data.0');

        // The list and the detail must agree, which they do by construction:
        // both read the same computed block.
        $this->assertSame('on_track', $row['sla']['state']);
    }

    public function test_the_headline_state_is_the_worse_of_the_two(): void
    {
        $ticket = $this->makeTicket(['priority' => 'normal']);
        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        $sla = $this->actingAs($this->supervisor())
            ->getJson("/api/v1/tickets/{$ticket->getKey()}")
            ->assertOk()
            ->json('sla');

        /*
         * A list has room for one badge. A ticket that answered fast and is now
         * three days late on resolution must not read as fine.
         */
        $this->assertSame('breached', $sla['state']);
    }

    public function test_the_whole_page_costs_one_extra_query(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->makeTicket(['priority' => 'normal']);
        }

        $reader = $this->supervisor();

        // Warm whatever the framework caches on a first hit.
        $this->actingAs($reader)->getJson('/api/v1/tickets')->assertOk();

        $queries = [];
        DB::listen(function ($q) use (&$queries): void {
            $queries[] = $q->sql;
        });

        $this->actingAs($reader)->getJson('/api/v1/tickets')->assertOk();

        /*
         * Three reads for the SLA block across the whole page — tickets,
         * first replies, status history — not three per row. A per-ticket
         * lookup would be thirty round trips for one screen.
         */
        $slaQueries = array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'ticket_events')
                || str_contains($sql, 'min(sent_at)'),
        );

        $this->assertLessThanOrEqual(2, count($slaQueries), implode(' | ', $queries));
    }

    public function test_an_administrator_can_preview_what_a_target_means(): void
    {
        $response = $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->getJson('/api/v1/admin/sla/preview?minutes=240&from=2026-09-10T13:00:00Z')
            ->assertOk();

        /*
         * Thursday 16:00 Riyadh + four working hours: one hour Thursday, then
         * the weekend, then three hours Sunday. "8 hours" is not something a
         * person can picture against a Sunday-to-Thursday week.
         */
        $due = CarbonImmutable::parse($response->json('due_at'))->setTimezone('Asia/Riyadh');

        $this->assertSame('2026-09-13 12:00', $due->format('Y-m-d H:i'));
        $this->assertSame('Asia/Riyadh', $response->json('timezone'));
    }

    public function test_the_preview_answers_in_utc(): void
    {
        $response = $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->getJson('/api/v1/admin/sla/preview?minutes=60')
            ->assertOk();

        // The formatting layer converts. A local time from the API is how a
        // deadline moves by three hours without a number changing.
        $this->assertStringEndsWith('Z', (string) $response->json('due_at'));
    }

    public function test_a_nonsense_target_is_refused(): void
    {
        $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->getJson('/api/v1/admin/sla/preview?minutes=0')
            ->assertStatus(422);
    }

    public function test_an_agent_cannot_reach_the_preview(): void
    {
        // It belongs to the editor, which is administration.
        $this->actingAs($this->makeUser(Roles::AGENT))
            ->getJson('/api/v1/admin/sla/preview?minutes=60')
            ->assertStatus(403);
    }
}
