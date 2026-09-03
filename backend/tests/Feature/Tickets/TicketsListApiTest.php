<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The ticket list an agent works from.
 */
final class TicketsListApiTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->supervisor = $this->makeUser(Roles::SUPERVISOR);
    }

    private function list(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->supervisor)->getJson("/api/v1/tickets{$query}");
    }

    /** @return list<string> */
    private function references(\Illuminate\Testing\TestResponse $response): array
    {
        return array_column($response->json('data'), 'reference');
    }

    public function test_it_lists_tickets_with_pagination_metadata(): void
    {
        $this->makeTicket();
        $this->makeTicket();

        $response = $this->list()->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(25, $response->json('meta.per_page'));
        $this->assertSame(1, $response->json('meta.last_page'));
    }

    public function test_an_empty_result_keeps_the_same_shape(): void
    {
        $response = $this->list('?status=closed')->assertOk();

        // A screen that has to special-case "no results have a different shape"
        // is a screen that will get it wrong once.
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_it_filters_by_status(): void
    {
        $open = $this->makeTicket(['status' => 'open']);
        $this->makeTicket(['status' => 'closed']);

        $this->assertSame([$open->reference], $this->references($this->list('?status=open')));
    }

    public function test_it_filters_by_several_statuses_at_once(): void
    {
        $this->makeTicket(['status' => 'open']);
        $this->makeTicket(['status' => 'pending']);
        $this->makeTicket(['status' => 'closed']);

        // Comma-separated is what a link in the counts strip looks like, and
        // what an agent can read and edit in the address bar.
        $this->assertCount(2, $this->list('?status=open,pending')->json('data'));
    }

    public function test_it_filters_by_priority(): void
    {
        $urgent = $this->makeTicket(['priority' => 'urgent']);
        $this->makeTicket(['priority' => 'low']);

        $this->assertSame([$urgent->reference], $this->references($this->list('?priority=urgent')));
    }

    public function test_it_filters_by_category(): void
    {
        $category = \App\Modules\Tickets\Domain\Category::query()->create([
            'name_en' => 'Billing',
            'name_ar' => 'الفوترة',
        ]);

        $matched = $this->makeTicket(['category_id' => $category->getKey()]);
        $this->makeTicket();

        $this->assertSame(
            [$matched->reference],
            $this->references($this->list('?category_id='.$category->getKey())),
        );
    }

    public function test_it_filters_by_department(): void
    {
        $department = $this->makeDepartment('Support');

        $matched = $this->makeTicket(['department_id' => $department]);
        $this->makeTicket();

        $this->assertSame(
            [$matched->reference],
            $this->references($this->list('?department_id='.$department)),
        );
    }

    public function test_it_filters_by_assignee(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $mine = $this->makeTicket(['assignee_id' => $agent->getKey()]);
        $this->makeTicket();

        $this->assertSame(
            [$mine->reference],
            $this->references($this->list('?assignee_id='.$agent->getKey())),
        );
    }

    public function test_the_unassigned_sentinel_finds_the_pool(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $this->makeTicket(['assignee_id' => $agent->getKey()]);
        $pool = $this->makeTicket(['assignee_id' => null]);

        /*
         * "Unassigned" is a real answer, not the absence of one — it is the
         * queue an agent picks their next ticket from. It must not be cast to
         * an integer, which would silently become assignee_id = 0.
         */
        $this->assertSame([$pool->reference], $this->references($this->list('?assignee_id=unassigned')));
    }

    public function test_assignee_and_the_pool_can_be_asked_for_together(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $mine = $this->makeTicket(['assignee_id' => $agent->getKey()]);
        $pool = $this->makeTicket(['assignee_id' => null]);
        $this->makeTicket(['assignee_id' => $this->makeUser(Roles::AGENT)->getKey()]);

        // What "my work" actually means to an agent: what I hold plus what I
        // could pick up.
        $found = $this->references($this->list('?assignee_id='.$agent->getKey().',unassigned'));

        sort($found);
        $expected = [$mine->reference, $pool->reference];
        sort($expected);

        $this->assertSame($expected, $found);
    }

    public function test_it_filters_by_a_date_range(): void
    {
        $old = $this->makeTicket();
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $recent = $this->makeTicket();

        $found = $this->references($this->list('?created_from='.now()->subDays(7)->toDateString()));

        $this->assertSame([$recent->reference], $found);
    }

    public function test_the_closing_day_of_a_range_is_included(): void
    {
        $ticket = $this->makeTicket();
        $ticket->forceFill(['created_at' => now()->setTime(18, 0)])->save();

        // A range typed as two dates means "these two days and everything
        // between", not "up to midnight of the second".
        $found = $this->references($this->list('?created_to='.now()->toDateString()));

        $this->assertContains($ticket->reference, $found);
    }

    public function test_it_searches_across_subject_and_description(): void
    {
        $matched = $this->makeTicket(['subject' => 'Duplicate charge on the invoice']);
        $this->makeTicket(['subject' => 'Password reset']);

        $this->assertSame([$matched->reference], $this->references($this->list('?q=duplicate')));
    }

    public function test_it_searches_the_description_too(): void
    {
        $matched = $this->makeTicket(['description' => 'The card ending 4417 was charged twice']);
        $this->makeTicket();

        $this->assertSame([$matched->reference], $this->references($this->list('?q=4417')));
    }

    public function test_it_finds_a_ticket_by_a_fragment_of_its_reference(): void
    {
        $ticket = $this->makeTicket();
        $fragment = substr($ticket->reference, -4);

        // What an agent has is usually the tail of a reference read off an
        // earlier email.
        $this->assertContains($ticket->reference, $this->references($this->list('?q='.$fragment)));
    }

    public function test_whitespace_is_not_a_search(): void
    {
        $this->makeTicket();
        $this->makeTicket();

        // Sending it would scan the table to match everything.
        $this->assertCount(2, $this->list('?q=%20%20')->json('data'));
    }

    public function test_it_finds_arabic_content(): void
    {
        $arabic = $this->makeTicket(['subject' => 'الفاتورة فيها رسوم مكرّرة']);
        $this->makeTicket(['subject' => 'Password reset']);

        // Half the tickets in this system are written in Arabic; a search that
        // only works in English is a search that works for half the work.
        $this->assertSame([$arabic->reference], $this->references($this->list('?q='.rawurlencode('مكرّرة'))));
    }

    public function test_it_sorts_by_a_whitelisted_column(): void
    {
        $first = $this->makeTicket(['subject' => 'A']);
        $second = $this->makeTicket(['subject' => 'B']);

        $found = $this->references($this->list('?sort=reference&direction=asc'));

        $this->assertSame([$first->reference, $second->reference], $found);
    }

    public function test_an_unknown_sort_column_is_refused(): void
    {
        /*
         * Refused, not silently ignored. A caller-supplied ORDER BY is an
         * injection surface, and an unindexed one is a table scan on every
         * page — neither is something to pass through quietly.
         */
        $this->list('?sort=password')->assertStatus(422);
    }

    public function test_an_oversized_page_is_refused(): void
    {
        $this->list('?per_page=10000')->assertStatus(422);
    }

    public function test_a_reversed_date_range_is_refused(): void
    {
        // Almost always a typo. Silently swapping it would show results for a
        // period nobody asked about.
        $this->list('?created_from=2026-09-01&created_to=2026-08-01')->assertStatus(422);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $this->list('?status=deleted')->assertStatus(422);
    }
}
