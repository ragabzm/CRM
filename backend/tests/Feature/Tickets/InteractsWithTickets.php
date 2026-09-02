<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Category;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

trait InteractsWithTickets
{
    protected string $customerId;

    protected int $departmentId;

    protected int $categoryId;

    protected function setUpTickets(string $role = Roles::AGENT): User
    {
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->departmentId = (int) Department::firstOrCreate(
            ['name' => 'Billing'],
            ['is_active' => true],
        )->getKey();

        $this->categoryId = (int) Category::firstOrCreate(
            ['name_en' => 'Invoices'],
            ['name_ar' => 'الفواتير', 'sort_order' => 1],
        )->getKey();

        $customer = new Customer([
            'reference' => Customer::mintReference(),
            'full_name' => 'Hana Yousef',
            'department_id' => $this->departmentId,
            'state' => 'active',
        ]);
        $customer->setAttribute('id', (string) Str::ulid());
        $customer->save();

        $this->customerId = (string) $customer->getKey();

        return $this->actingAsRole($role);
    }

    protected function actingAsRole(string $role, ?string $name = null): User
    {
        $user = User::factory()->create($name !== null ? ['name' => $name] : []);
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function ticketPayload(array $overrides = []): array
    {
        return [
            'subject' => 'Invoice is wrong',
            'description' => 'The September invoice charges for two seats, not one.',
            'customer_id' => $this->customerId,
            'channel' => 'agent',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function createTicket(array $overrides = []): array
    {
        return $this->withIdempotencyKey()
            ->postJson('/api/v1/tickets', $this->ticketPayload($overrides))
            ->assertStatus(201)
            ->json();
    }
}
