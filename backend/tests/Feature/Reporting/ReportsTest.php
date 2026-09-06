<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\User;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A small set of figures a supervisor can trust and click into.
 *
 * The two properties that make them trustworthy are both about NOT changing:
 * a past period answers the same way after somebody edits a target, and every
 * figure opens the tickets it counted. A number nobody can audit is a number
 * nobody believes, and a number that moves when a setting moves is worse than
 * none.
 */
final class ReportsTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->supervisor = $this->makeUser(Roles::SUPERVISOR);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    private function report(string $from = '2026-03-01', string $to = '2026-03-31'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->supervisor, 'web')
            ->getJson("/api/v1/reports?from={$from}&to={$to}");
    }

    /** A ticket raised inside the March window. */
    private function marchTicket(array $attributes = []): Ticket
    {
        $ticket = $this->makeTicket($attributes);

        $ticket->forceFill(['created_at' => '2026-03-10 09:00:00'])->save();

        return $ticket->refresh();
    }

    public function test_six_cards_over_the_chosen_period(): void
    {
        $this->marchTicket(['status' => 'open']);
        $this->marchTicket(['status' => 'open']);
        $this->marchTicket(['status' => 'pending']);
        $this->marchTicket(['status' => 'resolved']);
        $this->marchTicket(['status' => 'closed']);

        // Outside the window, and it must not be counted.
        $this->makeTicket(['status' => 'open'])->forceFill(['created_at' => '2026-01-05 09:00:00'])->save();

        $cards = collect($this->report()->json('data.volume.cards'))->keyBy('key');

        $this->assertSame(5, $cards['total']['value']);
        $this->assertSame(2, $cards['open']['value']);
        $this->assertSame(1, $cards['pending']['value']);
        $this->assertSame(1, $cards['resolved']['value']);
        $this->assertSame(1, $cards['closed']['value']);
        // Five statuses plus the total. There is no seventh card.
        $this->assertCount(5, $cards);
    }

    public function test_every_card_carries_the_filters_that_produced_it(): void
    {
        $this->marchTicket(['status' => 'open']);

        $cards = collect($this->report()->json('data.volume.cards'))->keyBy('key');

        /*
         * The FILTERS, not a description of them. The click-through builds the
         * one ticket list from these, so the destination cannot drift from the
         * figure — which is what makes the number auditable.
         */
        $this->assertSame('2026-03-01', $cards['open']['filters']['created_from']);
        $this->assertSame('2026-03-31', $cards['open']['filters']['created_to']);
        $this->assertSame('open', $cards['open']['filters']['status']);

        // The total has no status filter: it is every ticket in the range.
        $this->assertArrayNotHasKey('status', $cards['total']['filters']);
    }

    public function test_a_cards_filters_return_exactly_the_tickets_it_counted(): void
    {
        $this->marchTicket(['status' => 'open']);
        $this->marchTicket(['status' => 'open']);
        $this->marchTicket(['status' => 'closed']);
        $this->makeTicket(['status' => 'open'])->forceFill(['created_at' => '2026-01-05 09:00:00'])->save();

        $cards = collect($this->report()->json('data.volume.cards'))->keyBy('key');
        $open = $cards['open'];

        $list = $this->actingAs($this->supervisor, 'web')
            ->getJson('/api/v1/tickets?'.http_build_query($open['filters']));

        /*
         * The whole promise of the surface, tested end to end: the figure and
         * the list it opens are the same set of rows, not two queries that
         * happen to agree today.
         */
        $list->assertOk();
        $this->assertSame($open['value'], count($list->json('data')));
    }

    public function test_volume_breaks_down_by_status_category_and_assignee(): void
    {
        $category = (int) DB::table('ticket_categories')->insertGetId([
            'name_en' => 'Billing', 'name_ar' => 'الفوترة', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $agent = $this->makeUser(Roles::AGENT);

        $this->marchTicket(['status' => 'open', 'category_id' => $category, 'assignee_id' => $agent->getKey()]);
        $this->marchTicket(['status' => 'open']);

        $data = $this->report()->json('data.volume');

        $this->assertNotEmpty($data['by_status']);
        $this->assertNotEmpty($data['by_category']);
        $this->assertNotEmpty($data['by_assignee']);

        $byCategory = collect($data['by_category'])->keyBy('key');
        $this->assertSame(1, $byCategory[(string) $category]['value']);
        // Uncategorised is a real answer, and often the interesting one.
        $this->assertSame(1, $byCategory['none']['value']);

        $byAssignee = collect($data['by_assignee'])->keyBy('key');
        $this->assertSame(1, $byAssignee['unassigned']['value']);
    }

    public function test_satisfaction_is_a_rate_over_ratings_given(): void
    {
        // A thousand closed tickets and three answers is 67%, not 0.2%.
        foreach (range(1, 10) as $i) {
            $this->marchTicket(['status' => 'closed']);
        }

        $rated = Ticket::query()->limit(3)->get();

        foreach ([true, true, false] as $index => $positive) {
            $rated[$index]->forceFill([
                'satisfaction' => $positive,
                'satisfaction_at' => '2026-03-12 10:00:00',
            ])->save();
        }

        $satisfaction = $this->report()->json('data.satisfaction');

        $this->assertSame(3, $satisfaction['answered']);
        $this->assertSame(2, $satisfaction['positive']);
        $this->assertSame(1, $satisfaction['negative']);
        // The denominator is ratings given, never tickets closed.
        $this->assertEqualsWithDelta(0.6667, $satisfaction['positive_rate'], 0.0001);
    }

    public function test_nobody_answering_is_not_nought_per_cent(): void
    {
        $this->marchTicket(['status' => 'closed']);

        $satisfaction = $this->report()->json('data.satisfaction');

        /*
         * Zero per cent would say every customer who replied was unhappy,
         * which is a different and much worse thing than nobody replying.
         */
        $this->assertNull($satisfaction['positive_rate']);
        $this->assertSame(0, $satisfaction['answered']);
    }

    public function test_every_satisfaction_figure_opens_its_own_tickets(): void
    {
        $ticket = $this->marchTicket(['status' => 'closed']);
        $ticket->forceFill(['satisfaction' => true, 'satisfaction_at' => '2026-03-12 10:00:00'])->save();

        $satisfaction = $this->report()->json('data.satisfaction');

        foreach (['filters', 'positive_filters', 'negative_filters'] as $key) {
            $list = $this->actingAs($this->supervisor, 'web')
                ->getJson('/api/v1/tickets?'.http_build_query($satisfaction[$key]));

            // A figure with no click-through does not ship.
            $list->assertOk();
        }

        $positive = $this->actingAs($this->supervisor, 'web')
            ->getJson('/api/v1/tickets?'.http_build_query($satisfaction['positive_filters']));

        $this->assertSame(1, count($positive->json('data')));
    }

    public function test_compliance_survives_a_target_being_edited(): void
    {
        /*
         * The fixture has to make the two implementations DISAGREE, or the
         * test passes whichever one is running.
         *
         * This ticket was answered in 45 working minutes and no breach was
         * ever recorded for it. Under a 60-minute target it met; under the
         * 10-minute target set below, a report that recomputed from today's
         * settings would call it breached. A report reading `sla_events`
         * reports the same thing both times, which is the point.
         *
         * The first version of this test used a resolved ticket with no
         * messages, so both implementations answered zero and it passed
         * against a deliberately wrong one.
         */
        $answeredInTime = $this->marchTicket(['status' => 'resolved', 'priority' => 'normal']);

        $answeredInTime->forceFill(['resolved_at' => '2026-03-10 11:00:00'])->save();

        DB::table('ticket_messages')->insert([
            'id' => (string) Str::ulid(),
            'ticket_id' => $answeredInTime->getKey(),
            'customer_id' => $answeredInTime->customer_id,
            'direction' => 'outbound',
            'author_type' => 'staff',
            'author_id' => (string) $this->supervisor->getKey(),
            'author_name' => 'A supervisor',
            'body' => 'Looking into it.',
            'sent_at' => '2026-03-10 09:45:00',
            'delivery_state' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticket = $this->marchTicket(['status' => 'resolved', 'priority' => 'normal']);

        DB::table('sla_events')->insert([
            'id' => (string) Str::ulid(),
            'ticket_id' => $ticket->getKey(),
            'target' => 'response',
            'priority' => 'normal',
            'target_minutes' => 60,
            'elapsed_minutes' => 300,
            'breached_at' => '2026-03-11 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = $this->report()->json('data.sla');

        /*
         * The test the whole design exists for. An administrator tightening a
         * target this morning has not made last March worse — and a report
         * that said otherwise would be one nobody could take to a meeting.
         */
        $this->settings()->set('sla.response_target_seconds.normal', 600, null);
        $this->settings()->set('sla.resolution_target_seconds.normal', 600, null);

        $this->assertSame($before, $this->report()->json('data.sla'));
    }

    public function test_compliance_counts_recorded_breaches_not_current_state(): void
    {
        $breached = $this->marchTicket(['status' => 'resolved']);
        $this->marchTicket(['status' => 'resolved']);
        $this->marchTicket(['status' => 'resolved']);
        $this->marchTicket(['status' => 'resolved']);

        DB::table('sla_events')->insert([
            'id' => (string) Str::ulid(),
            'ticket_id' => $breached->getKey(),
            'target' => 'response',
            'priority' => 'normal',
            'target_minutes' => 60,
            'elapsed_minutes' => 300,
            'breached_at' => '2026-03-11 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sla = $this->report()->json('data.sla');

        $this->assertSame(4, $sla['tickets']);
        $this->assertSame(1, $sla['breaches']);
        $this->assertSame(1, $sla['response']['breaches']);
        // Three of four met the response target.
        $this->assertEqualsWithDelta(0.75, $sla['response']['compliance_rate'], 0.0001);
    }

    public function test_an_empty_period_is_not_perfect_compliance(): void
    {
        $sla = $this->report()->json('data.sla');

        /*
         * A desk congratulating itself on a month it did not work. Null says
         * "nothing to measure"; 100% says "flawless".
         */
        $this->assertNull($sla['response']['compliance_rate']);
        $this->assertNull($sla['response']['average_minutes']);
        $this->assertSame(0, $sla['tickets']);
    }

    public function test_averages_come_from_business_hours_not_wall_clock(): void
    {
        $ticket = $this->marchTicket(['status' => 'resolved']);

        // Raised Friday evening, resolved Monday morning. Wall clock says
        // three days; the desk was shut for most of it.
        $ticket->forceFill([
            'created_at' => '2026-03-06 17:00:00',
            'resolved_at' => '2026-03-09 09:30:00',
        ])->save();

        $average = $this->report('2026-03-01', '2026-03-31')->json('data.sla.resolution.average_minutes');

        $this->assertNotNull($average);
        /*
         * Three wall-clock days is 4,080 minutes. Anything near that means the
         * report is counting nights and weekends — and would disagree with the
         * ticket's own SLA badge, which the customer would find first.
         */
        $this->assertLessThan(2000, $average);
    }

    public function test_a_date_range_is_the_only_filter(): void
    {
        $this->marchTicket(['status' => 'open', 'priority' => 'urgent']);

        // Every one of these is a filter the story rules out. They are ignored
        // rather than honoured, so a link carrying one cannot quietly produce
        // a different report.
        $response = $this->actingAs($this->supervisor, 'web')->getJson(
            '/api/v1/reports?from=2026-03-01&to=2026-03-31&priority=low&department_id=99&channel=email&agent_id=1',
        );

        $response->assertOk();
        $this->assertSame(1, collect($response->json('data.volume.cards'))->firstWhere('key', 'total')['value']);
    }

    public function test_the_range_is_inclusive_at_both_ends(): void
    {
        $this->makeTicket(['status' => 'open'])->forceFill(['created_at' => '2026-03-01 00:05:00'])->save();
        $this->makeTicket(['status' => 'open'])->forceFill(['created_at' => '2026-03-31 23:55:00'])->save();

        // What somebody picking "1 to 31 March" means.
        $this->assertSame(
            2,
            collect($this->report()->json('data.volume.cards'))->firstWhere('key', 'total')['value'],
        );
    }

    public function test_a_backwards_range_is_refused_rather_than_swapped(): void
    {
        $response = $this->report('2026-03-31', '2026-03-01');

        $response->assertStatus(422);
        $this->assertSame('reporting.period_backwards', $response->json('code'));
    }

    public function test_an_agent_is_refused_with_a_reason_not_an_empty_page(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $response = $this->actingAs($agent, 'web')->getJson('/api/v1/reports?from=2026-03-01&to=2026-03-31');

        $response->assertForbidden();
        // UX-07: what was refused, and who to ask. Never empty data.
        $this->assertNotEmpty($response->json('detail'));
        $this->assertNull($response->json('data'));
    }

    public function test_an_administrator_may_read_them(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);

        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/reports?from=2026-03-01&to=2026-03-31')
            ->assertOk();
    }

    public function test_signing_out_leaves_nothing_readable(): void
    {
        $this->getJson('/api/v1/reports?from=2026-03-01&to=2026-03-31')->assertUnauthorized();
    }

    public function test_reporting_writes_nothing(): void
    {
        $this->marchTicket(['status' => 'open']);

        $before = [
            'tickets' => DB::table('tickets')->get()->toArray(),
            'events' => DB::table('ticket_events')->count(),
            'sla' => DB::table('sla_events')->count(),
        ];

        $this->report()->assertOk();

        // Reporting owns no table and performs no write. Reading a report must
        // leave no trace — there is no read log and no sealed-period stamp.
        $this->assertEquals($before['tickets'], DB::table('tickets')->get()->toArray());
        $this->assertSame($before['events'], DB::table('ticket_events')->count());
        $this->assertSame($before['sla'], DB::table('sla_events')->count());
    }
}
