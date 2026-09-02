<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Finding a customer from whatever the agent has in front of them.
 *
 * These run on SQLite, which exercises the portable containment path. The
 * Postgres trigram path — what production runs — is covered separately by
 * CustomerSearchPostgresTest, which skips when no Postgres is reachable.
 */
final class SearchTest extends TestCase
{
    use InteractsWithCustomers;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCustomers();
    }

    /** @return list<string> */
    private function search(string $query = ''): array
    {
        $body = $this->getJson('/api/v1/customers'.($query !== '' ? "?{$query}" : ''))->assertOk()->json();

        return array_column($body['data'], 'full_name');
    }

    private function seedThree(): array
    {
        return [
            $this->createCustomer('Hana Yousef', [
                ['kind' => 'email', 'value' => 'hana@example.test'],
                ['kind' => 'phone', 'value' => '+44 20 7946 0958'],
            ]),
            $this->createCustomer('Omar Saleh', [['kind' => 'email', 'value' => 'omar@work.test']]),
            $this->createCustomer('نور الهدى', [['kind' => 'phone', 'value' => '555 123 4567']]),
        ];
    }

    public function test_an_empty_query_lists_the_most_recently_touched(): void
    {
        $this->seedThree();

        // What an agent returning to their desk wants to see.
        $this->assertCount(3, $this->search());
    }

    public function test_a_partial_name_finds_the_customer(): void
    {
        $this->seedThree();

        $this->assertSame(['Hana Yousef'], $this->search('q=Yous'));
    }

    public function test_a_partial_arabic_name_finds_the_customer(): void
    {
        $this->seedThree();

        // The product is bilingual; a search that only works in Latin script
        // is a search half the staff cannot use.
        $this->assertSame(['نور الهدى'], $this->search('q='.urlencode('نور')));
    }

    public function test_the_name_search_ignores_case(): void
    {
        $this->seedThree();

        $this->assertSame(['Hana Yousef'], $this->search('q=hANA'));
    }

    public function test_a_partial_email_finds_the_customer(): void
    {
        $this->seedThree();

        $this->assertSame(['Omar Saleh'], $this->search('q=omar@work'));
    }

    public function test_a_phone_written_differently_still_finds_them(): void
    {
        $this->seedThree();

        // The agent types what the caller says, and the caller says it their
        // own way.
        $this->assertSame(['Hana Yousef'], $this->search('q='.urlencode('020 7946 0958')));
        $this->assertSame(['Hana Yousef'], $this->search('q='.urlencode('(020) 7946-0958')));
    }

    public function test_a_reference_prefix_finds_the_customer(): void
    {
        [$first] = $this->seedThree();

        // Read off a previous ticket, usually only the first few characters.
        $this->assertSame(['Hana Yousef'], $this->search('q='.substr($first['reference'], 0, 5)));
    }

    public function test_a_customer_appears_once_however_many_identifiers_match(): void
    {
        $this->createCustomer('Many Ways', [
            ['kind' => 'email', 'value' => 'many@example.test'],
            ['kind' => 'email', 'value' => 'many.ways@example.test'],
            ['kind' => 'email', 'value' => 'm.ways@example.test'],
        ]);

        // A join would return this person three times and break both the count
        // and the paging.
        $this->assertSame(['Many Ways'], $this->search('q=many'));
        $this->assertSame(1, $this->getJson('/api/v1/customers?q=many')->json('meta.total'));
    }

    public function test_deactivated_customers_are_absent_by_default(): void
    {
        [$first] = $this->seedThree();
        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$first['id']}/deactivate")->assertOk();

        // Someone who left three years ago should not clutter today's lookup.
        $this->assertNotContains('Hana Yousef', $this->search());
        $this->assertNotContains('Hana Yousef', $this->search('q=Hana'));
    }

    public function test_deactivated_customers_are_findable_when_asked_for(): void
    {
        [$first] = $this->seedThree();
        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$first['id']}/deactivate")->assertOk();

        $this->assertSame(['Hana Yousef'], $this->search('state=inactive'));
        $this->assertCount(3, $this->search('state=all'));
    }

    public function test_the_department_filter_narrows_without_gating(): void
    {
        $other = (int) \App\Modules\Security\Domain\Department::create(['name' => 'Support', 'is_active' => true])->getKey();

        $this->seedThree();
        $this->createCustomer('Support Person', [['kind' => 'email', 'value' => 's@example.test']], ['department_id' => $other]);

        // A filter, never an access boundary: the same agent can ask for either
        // department and gets an answer for both.
        $this->assertSame(['Support Person'], $this->search("department_id={$other}"));
        $this->assertCount(3, $this->search("department_id={$this->departmentId}"));
    }

    public function test_a_query_matching_nothing_returns_an_empty_page(): void
    {
        $this->seedThree();

        $body = $this->getJson('/api/v1/customers?q=nobodyatall')->assertOk()->json();

        // Empty, not an error: "no results" is an answer.
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['total']);
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->getJson('/api/v1/customers?limit=5000')->assertStatus(422);
    }

    public function test_an_unknown_state_is_refused(): void
    {
        $this->getJson('/api/v1/customers?state=deleted')->assertStatus(422);
    }

    public function test_the_list_carries_the_department_name_not_just_its_id(): void
    {
        $this->seedThree();

        // So the list renders without a second request per row.
        $this->assertSame('Billing', $this->getJson('/api/v1/customers')->json('data.0.department.name'));
    }

    public function test_a_direct_link_to_a_deactivated_customer_still_opens(): void
    {
        [$first] = $this->seedThree();
        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$first['id']}/deactivate")->assertOk();

        // A 404 on a link in an old ticket would look like data loss.
        $this->getJson("/api/v1/customers/{$first['id']}")
            ->assertOk()
            ->assertJsonPath('state', 'inactive')
            ->assertJsonPath('full_name', 'Hana Yousef');
    }
}
