<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Everything, in dependency order.
 *
 * Two layers, and the split matters. The first two seeders are safe anywhere:
 * roles and permissions are the application's own capability matrix, and the
 * development accounts refuse to run outside local development on their own.
 * Everything after them is DEMO data — invented customers, invented tickets —
 * and is fenced off behind an environment check here as well as inside each
 * seeder, so that reading this file tells you where the line is without
 * opening seven more.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * The demo layer, in the order their dependencies require.
     *
     * Each one also calls whatever it needs, so any single entry works on its
     * own via `--class=`. The order here is what makes a full run do each
     * piece once instead of rediscovering it.
     *
     * @var list<class-string<Seeder>>
     */
    private const DEMO = [
        DemoDepartmentsSeeder::class,
        DemoAgentsSeeder::class,
        DemoCategoriesSeeder::class,
        DemoCustomersSeeder::class,
        DemoPortalAccountsSeeder::class,
        // Tickets are built by the Tickets module's commands, so their events,
        // versions and timestamps come out right. See DemoTicketsSeeder.
        DemoTicketsSeeder::class,
        // Last: it attaches to a ticket and to a customer, both of which have
        // to exist first.
        DemoAttachmentsSeeder::class,
    ];

    public function run(): void
    {
        // Before anything that assigns a role.
        $this->call(RolesAndPermissionsSeeder::class);

        /*
         * The accounts the team actually signs in with. They used to be created
         * by hand, so `migrate:fresh --seed` destroyed them and left the
         * Administration console unreachable. The seeder itself refuses to run
         * outside local development.
         */
        $this->call(DevAccountsSeeder::class);

        if (! App::environment(['local', 'testing'])) {
            $this->command?->info('Demo data skipped: local development only.');

            return;
        }

        $this->call(self::DEMO);
    }
}
