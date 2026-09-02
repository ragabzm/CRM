<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Duplicate detection offers. It never blocks.
 *
 * Two people in a household genuinely share a landline, so a system that
 * refuses the second is one an agent works around while a real person waits.
 * What it prevents is the accidental case — the same customer entered twice
 * because nobody searched first.
 */
final class DuplicateDetectionTest extends TestCase
{
    use InteractsWithCustomers;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCustomers();
    }

    public function test_a_matching_email_is_offered_rather_than_rejected(): void
    {
        $existing = $this->createCustomer('Hana Yousef', [
            ['kind' => 'email', 'value' => 'hana@example.test'],
        ]);

        $response = $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            'Hana Y',
            [['kind' => 'email', 'value' => '  HANA@Example.TEST ']],
        ))->assertStatus(409);

        $response->assertJsonPath('code', 'customers.duplicate_offer')
            ->assertJsonPath('matches.0.customer_id', $existing['id'])
            ->assertJsonPath('matches.0.full_name', 'Hana Yousef');

        // Nothing was created — the agent gets to choose.
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_the_same_number_written_differently_is_recognised(): void
    {
        $this->createCustomer('Hana Yousef', [['kind' => 'phone', 'value' => '+44 20 7946 0958']]);

        $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            'Hana Y',
            [['kind' => 'phone', 'value' => '020 7946 0958']],
        ))->assertStatus(409)->assertJsonPath('matches.0.matched_kind', 'phone');
    }

    public function test_the_offer_names_which_detail_matched(): void
    {
        $this->createCustomer('Hana Yousef', [['kind' => 'email', 'value' => 'hana@example.test']]);

        // So the form can point at the row, rather than saying "something here
        // is a duplicate" and leaving the agent to find it.
        $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            'Hana Y',
            [['kind' => 'email', 'value' => 'hana@example.test']],
        ))->assertStatus(409)->assertJsonPath('matches.0.matched_value', 'hana@example.test');
    }

    public function test_confirming_creates_the_second_record(): void
    {
        $this->createCustomer('Parent', [['kind' => 'phone', 'value' => '555 123 4567']]);

        $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            'Child in the same household',
            [['kind' => 'phone', 'value' => '555 123 4567']],
            ['confirm_create_duplicate' => true],
        ))->assertStatus(201);

        // The database allows it, because two people really do share a phone.
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_a_deactivated_customer_is_still_offered(): void
    {
        $existing = $this->createCustomer('Hana Yousef', [['kind' => 'email', 'value' => 'hana@example.test']]);

        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$existing['id']}/deactivate")->assertOk();

        $response = $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            'Hana Y',
            [['kind' => 'email', 'value' => 'hana@example.test']],
        ))->assertStatus(409);

        // Someone returning after two years is exactly the duplicate worth
        // catching — their old record already holds the history a new one
        // would lack. The state travels so the UI can say so.
        $response->assertJsonPath('matches.0.state', 'inactive');
    }

    public function test_an_unrelated_customer_is_not_offered(): void
    {
        $this->createCustomer('Hana Yousef', [['kind' => 'email', 'value' => 'hana@example.test']]);

        $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            'Omar Saleh',
            [['kind' => 'email', 'value' => 'omar@example.test']],
        ))->assertStatus(201);
    }

    public function test_the_preview_reports_matches_without_creating_anything(): void
    {
        $existing = $this->createCustomer('Hana Yousef', [['kind' => 'phone', 'value' => '+44 20 7946 0958']]);

        $this->postJson('/api/v1/customers/duplicates/preview', [
            'phones' => ['020 7946 0958'],
        ])->assertOk()->assertJsonPath('matches.0.customer_id', $existing['id']);

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_the_preview_returns_nothing_for_an_unknown_contact(): void
    {
        $this->createCustomer();

        $this->postJson('/api/v1/customers/duplicates/preview', [
            'emails' => ['stranger@example.test'],
        ])->assertOk()->assertJsonPath('matches', []);
    }

    public function test_a_customer_being_edited_is_not_offered_as_their_own_duplicate(): void
    {
        $existing = $this->createCustomer('Hana Yousef', [['kind' => 'email', 'value' => 'hana@example.test']]);

        $this->postJson('/api/v1/customers/duplicates/preview', [
            'emails' => ['hana@example.test'],
            'exclude_customer_id' => $existing['id'],
        ])->assertOk()->assertJsonPath('matches', []);
    }

    public function test_the_preview_needs_only_read_access(): void
    {
        // An agent looks people up all day. Checking whether someone already
        // exists is part of looking up, not part of writing.
        $this->setUpCustomers(\App\Modules\Security\Domain\Roles::AGENT);

        $this->postJson('/api/v1/customers/duplicates/preview', ['emails' => ['a@b.test']])->assertOk();
    }
}
