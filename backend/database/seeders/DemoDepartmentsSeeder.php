<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Security\Domain\Department;
use Illuminate\Database\Seeder;

/**
 * The three teams every other demo seeder hangs off.
 *
 * First in the order because almost everything downstream needs a
 * `department_id`: an agent belongs to one, a customer is filed under one, and
 * a ticket inherits the customer's. Seeding them separately means a developer
 * can rebuild just this layer without touching the tickets on top of it.
 *
 * Name is the natural key. Departments are never deleted (see
 * `Department`), so a name that exists is the same team it was last run.
 */
final class DemoDepartmentsSeeder extends Seeder
{
    /** @var list<string> */
    public const DEPARTMENTS = ['Support', 'Sales', 'Billing'];

    public function run(): void
    {
        if (! DemoEnvironment::allows($this->command, self::class)) {
            return;
        }

        foreach (self::DEPARTMENTS as $name) {
            Department::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }

        $this->command?->info('Seeded '.count(self::DEPARTMENTS).' demo departments.');
    }
}
