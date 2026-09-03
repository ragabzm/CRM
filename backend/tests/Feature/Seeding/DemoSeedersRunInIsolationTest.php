<?php

declare(strict_types=1);

namespace Tests\Feature\Seeding;

use Database\Seeders\DemoAttachmentsSeeder;
use Database\Seeders\DemoTicketsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Any one seeder works on its own, on a database with nothing in it.
 *
 * This is how somebody actually recovers. After a fresh, or after wiping one
 * layer by hand, the command they reach for is
 * `db:seed --class=DemoTicketsSeeder` — and a seeder that assumed the ones
 * before it had run would fail with a foreign key nobody could read, or
 * quietly write nothing at all.
 *
 * `DevAccountsSeeder` already bootstraps itself this way for the same reason.
 */
final class DemoSeedersRunInIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The two with the deepest dependency graphs. Tickets needs agents,
     * categories, customers and portal accounts; attachments needs a specific
     * seeded ticket and a specific seeded customer.
     *
     * @return array<string, array{class-string}>
     */
    public static function deepestSeeders(): array
    {
        return [
            'tickets' => [DemoTicketsSeeder::class],
            'attachments' => [DemoAttachmentsSeeder::class],
        ];
    }

    #[DataProvider('deepestSeeders')]
    public function test_it_bootstraps_everything_it_needs(string $seeder): void
    {
        $this->assertSame(0, DB::table('tickets')->count(), 'The database was not empty to begin with.');

        Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);

        // It pulled its whole graph in behind it.
        $this->assertGreaterThan(0, DB::table('departments')->count());
        $this->assertGreaterThan(0, DB::table('users')->count());
        $this->assertGreaterThan(0, DB::table('customers')->count());
        $this->assertGreaterThanOrEqual(18, DB::table('tickets')->count());
    }

    public function test_the_attachments_seeder_finds_the_ticket_it_attaches_to(): void
    {
        Artisan::call('db:seed', ['--class' => DemoAttachmentsSeeder::class, '--force' => true]);

        /*
         * The one that would fail silently. It looks its owner up by subject,
         * so a seeder that ran before the tickets existed would attach nothing
         * and report success.
         */
        $this->assertSame(2, DB::table('attachments')->count());
    }
}
