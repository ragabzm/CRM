<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The capability matrix is the whole authorization model. It is asserted here
 * so a change to it is a deliberate, visible edit rather than a side effect.
 */
final class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user->refresh();
    }

    public function test_exactly_four_roles_are_seeded(): void
    {
        $this->assertSame(
            ['administrator', 'agent', 'customer', 'supervisor'],
            Role::query()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_every_capability_is_seeded_as_a_permission(): void
    {
        $seeded = Permission::query()->pluck('name')->sort()->values()->all();
        $declared = collect(Capabilities::all())->sort()->values()->all();

        $this->assertSame($declared, $seeded);
    }

    public function test_capability_names_are_resource_dot_action(): void
    {
        foreach (Capabilities::all() as $capability) {
            // Never a `.scope` suffix: scope is a row-level question and
            // belongs in a query, not in a permission name.
            $this->assertMatchesRegularExpression('/^[a-z]+\.[a-z_]+$/', $capability);
            $this->assertSame(1, substr_count($capability, '.'), "{$capability} must have exactly one dot.");
        }
    }

    public function test_an_administrator_holds_every_capability(): void
    {
        $administrator = $this->actor(Roles::ADMINISTRATOR);

        foreach (Capabilities::all() as $capability) {
            $this->assertTrue(
                $administrator->can($capability),
                "Administrator should hold {$capability}.",
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function matrixProvider(): array
    {
        return [
            'supervisor' => [Roles::SUPERVISOR, RolesAndPermissionsSeeder::matrix()[Roles::SUPERVISOR]],
            'agent' => [Roles::AGENT, RolesAndPermissionsSeeder::matrix()[Roles::AGENT]],
            'customer' => [Roles::CUSTOMER, RolesAndPermissionsSeeder::matrix()[Roles::CUSTOMER]],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('matrixProvider')]
    public function test_a_role_holds_exactly_its_matrix_row(string $role, array $expected): void
    {
        $actor = $this->actor($role);

        foreach (Capabilities::all() as $capability) {
            $shouldHold = in_array($capability, $expected, true);

            $this->assertSame(
                $shouldHold,
                $actor->can($capability),
                sprintf('%s should %shold %s.', $role, $shouldHold ? '' : 'NOT ', $capability),
            );
        }
    }

    public function test_the_four_refusals_the_intake_names(): void
    {
        $agent = $this->actor(Roles::AGENT);
        $supervisor = $this->actor(Roles::SUPERVISOR);

        $this->assertFalse($agent->can(Capabilities::TICKET_REASSIGN), 'Agent must not reassign.');
        $this->assertFalse($agent->can(Capabilities::USER_MANAGE), 'Agent must not manage users.');
        $this->assertFalse($supervisor->can(Capabilities::SETTING_MANAGE), 'Supervisor must not configure.');
        $this->assertFalse($supervisor->can(Capabilities::AUDIT_READ), 'Supervisor must not read the audit log.');
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $roles = Role::query()->count();
        $permissions = Permission::query()->count();
        $pivot = \Illuminate\Support\Facades\DB::table('role_has_permissions')->count();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // firstOrCreate + syncPermissions converge rather than accumulate.
        $this->assertSame($roles, Role::query()->count());
        $this->assertSame($permissions, Permission::query()->count());
        $this->assertSame($pivot, \Illuminate\Support\Facades\DB::table('role_has_permissions')->count());
    }

    public function test_roles_cannot_be_created_over_http(): void
    {
        // There is no endpoint. This asserts the absence, so adding one becomes
        // a deliberate act that breaks a test naming the intent.
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())->all();

        foreach ($routes as $uri) {
            $this->assertStringNotContainsString('roles', $uri);
            $this->assertStringNotContainsString('permissions', $uri);
        }
    }
}
