<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class UpdateAndDeactivateTest extends TestCase
{
    use InteractsWithCustomers;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCustomers();
    }

    public function test_fields_are_edited(): void
    {
        $customer = $this->createCustomer();

        $this->withIdempotencyKey()->patchJson("/api/v1/customers/{$customer['id']}", [
            'full_name' => 'Hana Y. Yousef',
            'notes' => 'Prefers a call in the morning.',
        ])->assertOk()->assertJsonPath('full_name', 'Hana Y. Yousef');

        $this->assertSame('Prefers a call in the morning.', $this->getJson("/api/v1/customers/{$customer['id']}")->json('notes'));
    }

    public function test_the_reference_never_changes(): void
    {
        $customer = $this->createCustomer();

        $this->withIdempotencyKey()->patchJson("/api/v1/customers/{$customer['id']}", [
            'full_name' => 'Renamed',
            'reference' => 'C-HACKED99',
        ])->assertOk();

        // It is written on other people's tickets and read aloud on calls; a
        // reference that can be edited is not an identifier.
        $this->assertSame($customer['reference'], $this->getJson("/api/v1/customers/{$customer['id']}")->json('reference'));
    }

    public function test_submitting_identifiers_replaces_the_whole_set(): void
    {
        $customer = $this->createCustomer('Hana', [
            ['kind' => 'email', 'value' => 'old@example.test'],
            ['kind' => 'phone', 'value' => '555 123 4567'],
        ]);

        $this->withIdempotencyKey()->patchJson("/api/v1/customers/{$customer['id']}", [
            'identifiers' => [['kind' => 'email', 'value' => 'new@example.test']],
        ])->assertOk();

        // The agent is looking at the complete list, so the rows they deleted
        // are absent from what they submitted. Merging would resurrect them.
        $identifiers = $this->getJson("/api/v1/customers/{$customer['id']}")->json('identifiers');
        $this->assertCount(1, $identifiers);
        $this->assertSame('new@example.test', $identifiers[0]['value']);
    }

    public function test_an_edit_cannot_leave_a_customer_unreachable(): void
    {
        $customer = $this->createCustomer();

        $this->withIdempotencyKey()->patchJson("/api/v1/customers/{$customer['id']}", [
            'identifiers' => [],
        ])->assertStatus(422);

        $this->assertCount(1, $this->getJson("/api/v1/customers/{$customer['id']}")->json('identifiers'));
    }

    public function test_the_department_can_be_changed(): void
    {
        $customer = $this->createCustomer();
        $other = (int) \App\Modules\Security\Domain\Department::create(['name' => 'Support', 'is_active' => true])->getKey();

        $this->withIdempotencyKey()->patchJson("/api/v1/customers/{$customer['id']}", [
            'department_id' => $other,
        ])->assertOk()->assertJsonPath('department.name', 'Support');
    }

    public function test_deactivation_keeps_the_record_and_everything_on_it(): void
    {
        $customer = $this->createCustomer();

        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$customer['id']}/deactivate")
            ->assertOk()
            ->assertJsonPath('state', 'inactive');

        // Never a delete: the record is the anchor for every ticket and note
        // attached to it.
        $this->assertDatabaseHas('customers', ['id' => $customer['id'], 'state' => 'inactive']);
        $this->assertCount(1, $this->getJson("/api/v1/customers/{$customer['id']}")->json('identifiers'));
        $this->assertNotNull($this->getJson("/api/v1/customers/{$customer['id']}")->json('deactivated_at'));
    }

    public function test_deactivating_twice_is_not_an_error(): void
    {
        $customer = $this->createCustomer();

        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$customer['id']}/deactivate")->assertOk();
        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$customer['id']}/deactivate")
            ->assertOk()
            ->assertJsonPath('state', 'inactive');
    }

    public function test_reactivation_restores_the_record(): void
    {
        $customer = $this->createCustomer();

        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$customer['id']}/deactivate")->assertOk();
        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$customer['id']}/reactivate")
            ->assertOk()
            ->assertJsonPath('state', 'active')
            ->assertJsonPath('deactivated_at', null);

        // And it is back in the default list.
        $this->assertCount(1, $this->getJson('/api/v1/customers')->json('data'));
    }

    public function test_editing_a_customer_that_does_not_exist_is_a_problem_document(): void
    {
        $this->withIdempotencyKey()
            ->patchJson('/api/v1/customers/01JZZZZZZZZZZZZZZZZZZZZZZZ', ['full_name' => 'x'])
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'customers.not_found');
    }

    public function test_every_change_is_recorded_in_the_audit_log(): void
    {
        $customer = $this->createCustomer();

        $this->withIdempotencyKey()->patchJson("/api/v1/customers/{$customer['id']}", ['full_name' => 'Changed'])->assertOk();

        $this->assertDatabaseHas('audit_entries', [
            'action' => 'customer.field_changed',
            'target_id' => $customer['id'],
        ]);
    }
}
