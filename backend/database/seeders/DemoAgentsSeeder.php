<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Three more agents, one per department.
 *
 * `DevAccountsSeeder` gives a developer one account per ROLE, which is what
 * you need to test authorization. This gives one per DEPARTMENT, which is what
 * you need for anything about assignment: a ticket can only go to someone in
 * its own department, so a desk with a single agent can never show that rule
 * working — or failing.
 *
 * The three accounts from `DevAccountsSeeder` are not touched here. In
 * particular `agent@ragab.test` keeps its NULL department, which makes it
 * assignable to any ticket — a useful thing to have exactly one of.
 */
final class DemoAgentsSeeder extends Seeder
{
    /** @var list<array{string, string, string}> name, email, department */
    public const AGENTS = [
        ['Hana Support', 'agent.support@ragab.test', 'Support'],
        ['Tariq Sales', 'agent.sales@ragab.test', 'Sales'],
        ['Dana Billing', 'agent.billing@ragab.test', 'Billing'],
    ];

    public function run(): void
    {
        if (! DemoEnvironment::allows($this->command, self::class)) {
            return;
        }

        /*
         * Roles first, then the departments these accounts point at. This is
         * what makes `db:seed --class=DemoAgentsSeeder` work on a freshly
         * migrated database — which is exactly how somebody recovers after a
         * fresh, and the reason DevAccountsSeeder bootstraps itself the same
         * way.
         *
         * `callOnce`, not `call`. Seven seeders each naming their own
         * dependencies means the graph gets walked repeatedly: departments ran
         * twelve times in one full seed, and the output was long enough that
         * nobody would read it.
         */
        DemoEnvironment::needs($this, RolesAndPermissionsSeeder::class, static fn (): bool => \Spatie\Permission\Models\Role::query()->exists());
        DemoEnvironment::needs($this, DemoDepartmentsSeeder::class, static fn (): bool => Department::query()->exists());

        $departments = Department::query()->pluck('id', 'name');

        foreach (self::AGENTS as [$name, $email, $department]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    // Referenced, never redefined: one published password in
                    // `.squad/DEV-CREDENTIALS.md` that is true for every
                    // seeded account.
                    'password' => Hash::make(DevAccountsSeeder::PASSWORD),
                    'email_verified_at' => now(),
                    'department_id' => $departments[$department] ?? null,
                    'is_active' => true,
                ],
            );

            $user->syncRoles([Roles::AGENT]);
        }

        $this->command?->info('Seeded '.count(self::AGENTS).' demo agents, one per department.');
    }
}
