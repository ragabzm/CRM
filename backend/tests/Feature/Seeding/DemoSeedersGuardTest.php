<?php

declare(strict_types=1);

namespace Tests\Feature\Seeding;

use Database\Seeders\DemoAgentsSeeder;
use Database\Seeders\DemoAttachmentsSeeder;
use Database\Seeders\DemoCategoriesSeeder;
use Database\Seeders\DemoCustomersSeeder;
use Database\Seeders\DemoDepartmentsSeeder;
use Database\Seeders\DemoPortalAccountsSeeder;
use Database\Seeders\DemoTicketsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Outside local development, the demo seeders write nothing.
 *
 * The guard is what makes the rest of this story acceptable. Demo customers
 * carry invented names and a published password; a single one of them landing
 * in a real database is a support desk showing staff a person who does not
 * exist, and an account anyone can sign in to.
 *
 * The check is an ALLOW-list of `local` and `testing`, not a deny-list of
 * `production` — staging holds real people's data too, and a deny-list only
 * excludes the environment somebody remembered to name.
 */
final class DemoSeedersGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string}>
     */
    public static function demoSeeders(): array
    {
        return [
            'departments' => [DemoDepartmentsSeeder::class],
            'agents' => [DemoAgentsSeeder::class],
            'categories' => [DemoCategoriesSeeder::class],
            'customers' => [DemoCustomersSeeder::class],
            'portal accounts' => [DemoPortalAccountsSeeder::class],
            'tickets' => [DemoTicketsSeeder::class],
            'attachments' => [DemoAttachmentsSeeder::class],
        ];
    }

    #[DataProvider('demoSeeders')]
    public function test_it_writes_nothing_outside_local_development(string $seeder): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);

        foreach (['users', 'departments', 'ticket_categories', 'customers', 'tickets', 'attachments', 'portal_accounts'] as $table) {
            $this->assertSame(
                0,
                DB::table($table)->count(),
                class_basename($seeder)." wrote to [{$table}] in production.",
            );
        }
    }

    #[DataProvider('demoSeeders')]
    public function test_it_says_out_loud_that_it_skipped(string $seeder): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);

        // Silence would be indistinguishable from having run. Whoever typed
        // the command needs to know why nothing happened.
        $this->assertStringContainsString(
            'skipped: local development only',
            Artisan::output(),
        );
    }

    public function test_the_orchestrator_still_seeds_roles_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        Artisan::call('db:seed', ['--class' => \Database\Seeders\DatabaseSeeder::class, '--force' => true]);

        /*
         * The capability matrix is the application's own, not demo data. A
         * production deploy that skipped it would come up with no roles at
         * all — so the guard fences off the demo layer, not the whole seeder.
         */
        $this->assertGreaterThan(0, DB::table('roles')->count());
        $this->assertGreaterThan(0, DB::table('permissions')->count());

        // And the demo layer stayed out.
        $this->assertSame(0, DB::table('customers')->count());
        $this->assertSame(0, DB::table('tickets')->count());
    }
}
