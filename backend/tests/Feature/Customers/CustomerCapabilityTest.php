<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class CustomerCapabilityTest extends TestCase
{
    use InteractsWithCustomers;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCustomers();
    }

    private function becomes(string $role): void
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());
    }

    public function test_an_agent_can_look_customers_up(): void
    {
        $this->createCustomer();
        $this->becomes(Roles::AGENT);

        // Looking people up is most of an agent's day.
        $this->getJson('/api/v1/customers')->assertOk();
    }

    public function test_an_agent_cannot_change_a_customer(): void
    {
        $customer = $this->createCustomer();
        $this->becomes(Roles::AGENT);

        // Correcting a record is a supervisor's job.
        $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload('New'))->assertStatus(403);
        $this->withIdempotencyKey()->patchJson("/api/v1/customers/{$customer['id']}", ['full_name' => 'x'])->assertStatus(403);
        $this->withIdempotencyKey()->postJson("/api/v1/customers/{$customer['id']}/deactivate")->assertStatus(403);
    }

    public function test_a_refused_write_changes_nothing(): void
    {
        $customer = $this->createCustomer('Original Name');
        $this->becomes(Roles::AGENT);

        $this->withIdempotencyKey()->patchJson("/api/v1/customers/{$customer['id']}", ['full_name' => 'Tampered'])->assertStatus(403);

        $this->assertDatabaseHas('customers', ['id' => $customer['id'], 'full_name' => 'Original Name']);
    }

    public function test_a_supervisor_can_do_both(): void
    {
        $this->becomes(Roles::SUPERVISOR);

        $this->getJson('/api/v1/customers')->assertOk();
        $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload('New'))->assertStatus(201);
    }

    public function test_a_guest_is_challenged(): void
    {
        $this->app['auth']->forgetGuards();
        $this->refreshApplication();
        $this->setUpSpaOrigin();

        $this->getJson('/api/v1/customers')->assertStatus(401);
    }

    public function test_the_department_never_gates_access(): void
    {
        $other = (int) \App\Modules\Security\Domain\Department::create(['name' => 'Support', 'is_active' => true])->getKey();
        $this->createCustomer('Billing Person');
        $this->createCustomer('Support Person', [['kind' => 'email', 'value' => 's@example.test']], ['department_id' => $other]);

        $agent = User::factory()->create(['department_id' => $this->departmentId]);
        $agent->syncRoles([Roles::AGENT]);
        $this->actingAs($agent->refresh());

        // The agent belongs to Billing and still sees the Support customer.
        // Department is grouping, never an access boundary.
        $names = array_column($this->getJson('/api/v1/customers')->json('data'), 'full_name');
        $this->assertContains('Support Person', $names);
    }
}
