<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;

trait InteractsWithCustomers
{
    protected int $departmentId;

    protected function setUpCustomers(string $role = Roles::SUPERVISOR): User
    {
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        // firstOrCreate, so a test that switches role mid-way does not trip the
        // unique name index re-seeding the same department.
        $this->departmentId = (int) Department::firstOrCreate(
            ['name' => 'Billing'],
            ['is_active' => true],
        )->getKey();

        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());

        return $user;
    }

    /**
     * @param  list<array{kind:string,value:string,is_primary?:bool}>  $identifiers
     * @return array<string, mixed>
     */
    protected function customerPayload(string $name = 'Hana Yousef', array $identifiers = [], array $extra = []): array
    {
        return [
            'full_name' => $name,
            'department_id' => $this->departmentId,
            'identifiers' => $identifiers === []
                ? [['kind' => 'email', 'value' => 'hana@example.test', 'is_primary' => true]]
                : $identifiers,
            ...$extra,
        ];
    }

    protected function createCustomer(string $name = 'Hana Yousef', array $identifiers = [], array $extra = []): array
    {
        return $this->withIdempotencyKey()
            ->postJson('/api/v1/customers', $this->customerPayload($name, $identifiers, $extra))
            ->assertStatus(201)
            ->json();
    }
}
