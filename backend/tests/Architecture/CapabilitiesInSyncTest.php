<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Domain\Roles;
use PHPUnit\Framework\TestCase;

/**
 * Routes, the capability list, and the seeder cannot drift apart.
 *
 * A route naming a capability that does not exist fails CLOSED — it refuses
 * everyone, which looks like working security right up to the moment an
 * administrator reports being locked out of their own console.
 */
final class CapabilitiesInSyncTest extends TestCase
{
    private function routesSource(): string
    {
        return (string) file_get_contents(SourceScanner::basePath('routes/api.php'));
    }

    public function test_every_capability_referenced_by_a_route_exists(): void
    {
        $source = $this->routesSource();

        // Both spellings: the literal string and the constant reference.
        preg_match_all('/can\.capability:([a-z.]+)/', $source, $literal);
        preg_match_all('/can\.capability:\'\.Capabilities::([A-Z_]+)/', $source, $constant);

        foreach ($literal[1] as $capability) {
            $this->assertTrue(
                Capabilities::exists($capability),
                "Route references unknown capability [{$capability}].",
            );
        }

        foreach ($constant[1] as $name) {
            $this->assertTrue(
                defined(Capabilities::class."::{$name}"),
                "Route references unknown capability constant [{$name}].",
            );
        }
    }

    public function test_at_least_one_route_is_guarded(): void
    {
        // Guards against the rule passing vacuously if the middleware is ever
        // dropped from every route.
        $this->assertStringContainsString('can.capability:', $this->routesSource());
    }

    public function test_every_capability_constant_is_a_resource_dot_action_string(): void
    {
        foreach (Capabilities::all() as $capability) {
            $this->assertMatchesRegularExpression('/^[a-z]+\.[a-z_]+$/', $capability);
        }
    }

    public function test_the_capability_list_has_no_duplicates(): void
    {
        $all = Capabilities::all();

        $this->assertSame(count($all), count(array_unique($all)));
    }

    public function test_the_seeder_matrix_only_grants_known_capabilities(): void
    {
        foreach (\Database\Seeders\RolesAndPermissionsSeeder::matrix() as $role => $capabilities) {
            $this->assertContains($role, Roles::all(), "Matrix names unknown role [{$role}].");

            foreach ($capabilities as $capability) {
                $this->assertTrue(
                    Capabilities::exists($capability),
                    "Matrix grants unknown capability [{$capability}] to [{$role}].",
                );
            }
        }
    }

    public function test_the_matrix_does_not_enumerate_the_administrator(): void
    {
        // Administrator holds everything through Gate::before, so a capability
        // added later is one they already have — no seeder re-run, and no window
        // where the person who fixes permissions is the one locked out.
        $this->assertArrayNotHasKey(
            Roles::ADMINISTRATOR,
            \Database\Seeders\RolesAndPermissionsSeeder::matrix(),
        );
    }

    /**
     * The six lifecycle capabilities and who holds them.
     *
     * Pinned rather than derived: the seeder is the authorization model, and a
     * capability quietly appearing on the wrong role is a privilege change
     * nobody reviewed.
     */
    public function test_the_lifecycle_capabilities_are_seeded_to_the_right_roles(): void
    {
        $matrix = \Database\Seeders\RolesAndPermissionsSeeder::matrix();

        $shared = [
            Capabilities::TICKET_ASSIGN,
            Capabilities::TICKET_CHANGE_STATUS,
            Capabilities::TICKET_CHANGE_DEPARTMENT,
            Capabilities::TICKET_RESOLVE,
            Capabilities::TICKET_REOPEN,
        ];

        foreach ($shared as $capability) {
            $this->assertContains($capability, $matrix[Roles::AGENT], "Agent is missing [{$capability}].");
            $this->assertContains($capability, $matrix[Roles::SUPERVISOR], "Supervisor is missing [{$capability}].");
        }

        /*
         * The one that separates them. An agent picking up unclaimed work is
         * ordinary; taking a ticket out of a colleague's hands is not.
         */
        $this->assertContains(Capabilities::TICKET_REASSIGN_ANY, $matrix[Roles::SUPERVISOR]);
        $this->assertNotContains(Capabilities::TICKET_REASSIGN_ANY, $matrix[Roles::AGENT]);

        // The administrator holds everything through Gate::before and is
        // deliberately absent from the matrix.
        $this->assertArrayNotHasKey(Roles::ADMINISTRATOR, $matrix);
    }

    public function test_a_customer_holds_no_lifecycle_capability(): void
    {
        $matrix = \Database\Seeders\RolesAndPermissionsSeeder::matrix();

        foreach ([
            Capabilities::TICKET_ASSIGN,
            Capabilities::TICKET_REASSIGN_ANY,
            Capabilities::TICKET_CHANGE_STATUS,
            Capabilities::TICKET_CHANGE_DEPARTMENT,
            Capabilities::TICKET_RESOLVE,
            Capabilities::TICKET_REOPEN,
        ] as $capability) {
            // A customer may raise and read their own tickets. Moving them
            // through the lifecycle is staff work.
            $this->assertNotContains($capability, $matrix[Roles::CUSTOMER]);
        }
    }

    public function test_every_declared_capability_is_held_by_someone_or_is_administrator_only(): void
    {
        $matrix = \Database\Seeders\RolesAndPermissionsSeeder::matrix();
        $held = array_unique(array_merge(...array_values($matrix)));

        // Administrator-only by design: configuring the product and reading
        // everyone's actions.
        $administratorOnly = [
            Capabilities::USER_MANAGE,
            Capabilities::DEPARTMENT_MANAGE,
            Capabilities::AUDIT_READ,
            Capabilities::SETTING_MANAGE,
            Capabilities::TICKET_REASSIGN,
        ];

        foreach (Capabilities::all() as $capability) {
            if (in_array($capability, $administratorOnly, true)) {
                continue;
            }

            // A capability nobody holds is either dead or a gate that refuses
            // everyone — both worth finding at build time.
            $this->assertContains($capability, $held, "Nobody holds [{$capability}].");
        }
    }
}
