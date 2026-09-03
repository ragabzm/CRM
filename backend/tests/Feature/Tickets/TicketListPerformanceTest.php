<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The list stays usable at the size the business actually reaches.
 *
 * Fifty thousand tickets is roughly two years of a busy desk. An agent opening
 * the queue there must not wait — a list that takes five seconds is a list
 * people stop opening, and then the queue stops being worked from the queue.
 *
 * Postgres only, and against a real one: the SQLite fallback has none of the
 * indexes this measures, so timing it there would measure nothing. Skips loudly
 * rather than silently passing.
 *
 */
#[Group('performance')]
final class TicketListPerformanceTest extends TestCase
{
    use MakesTickets;

    /** NFR: the list answers in a second. */
    private const LIST_BUDGET_MS = 1000;

    /** Search may take longer — it is ranking, not just filtering. */
    private const SEARCH_BUDGET_MS = 2000;

    private const ROWS = 50_000;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = [
            'host' => (string) env('DB_TEST_HOST', '127.0.0.1'),
            'port' => (int) env('DB_TEST_PORT', 5432),
            'database' => (string) env('DB_TEST_DATABASE', 'ragab_test'),
            'username' => (string) env('DB_TEST_USERNAME', 'ragab'),
            'password' => (string) env('DB_TEST_PASSWORD', 'ragab'),
        ];

        try {
            new \PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $settings['host'], $settings['port'], $settings['database']),
                $settings['username'],
                $settings['password'],
                [\PDO::ATTR_TIMEOUT => 2],
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Postgres unreachable — the list performance target was NOT verified. Reason: '.$e->getMessage(),
            );
        }

        Config::set('database.default', 'pgsql');

        foreach ($settings as $key => $value) {
            Config::set("database.connections.pgsql.{$key}", $value);
        }

        DB::purge('pgsql');
        Artisan::call('migrate:fresh', ['--database' => 'pgsql', '--force' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->seedTickets();
    }

    protected function tearDown(): void
    {
        if (Config::get('database.default') === 'pgsql') {
            Artisan::call('migrate:reset', ['--database' => 'pgsql', '--force' => true]);
        }

        parent::tearDown();
    }

    /**
     * Fifty thousand rows, inserted in bulk.
     *
     * Through the model one at a time this would take minutes and would be
     * measuring Eloquent rather than the query under test.
     */
    private function seedTickets(): void
    {
        $customer = $this->makeCustomer();
        $now = now()->toDateTimeString();

        $subjects = [
            'Duplicate charge on the invoice',
            'Password reset request',
            'الفاتورة فيها رسوم مكرّرة',
            'Cannot download the report',
        ];

        for ($batch = 0; $batch < self::ROWS / 1000; $batch++) {
            $rows = [];

            for ($i = 0; $i < 1000; $i++) {
                $n = $batch * 1000 + $i;

                $rows[] = [
                    'id' => (string) Str::ulid(),
                    'reference' => 'TKT-'.str_pad((string) ($n + 1), 6, '0', STR_PAD_LEFT),
                    'subject' => $subjects[$n % count($subjects)],
                    'description' => 'Seeded row '.$n,
                    'customer_id' => $customer,
                    'channel' => 'agent',
                    'status' => ['open', 'pending', 'resolved', 'closed'][$n % 4],
                    'priority' => ['low', 'normal', 'high', 'urgent'][$n % 4],
                    'creator_type' => 'system',
                    'creator_id' => null,
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('tickets')->insert($rows);
        }

        // Without fresh statistics the planner has no idea how big the table is
        // and will pick a plan for the empty one it last saw.
        DB::statement('ANALYZE tickets');
    }

    private function timeMs(string $label, callable $call): float
    {
        $started = microtime(true);
        $call();
        $ms = (microtime(true) - $started) * 1000;

        // Printed, not just asserted: a budget met by 4ms and one met by 900ms
        // are different situations, and only the number says which this is.
        fwrite(STDERR, sprintf("\n    %-28s %6.0f ms", $label, $ms));

        return $ms;
    }

    public function test_the_seed_actually_produced_the_rows(): void
    {
        // A budget met against an empty table is not a budget met.
        $this->assertSame(self::ROWS, DB::table('tickets')->count());
    }

    public function test_the_first_page_is_within_the_budget(): void
    {
        $supervisor = $this->makeUser(Roles::SUPERVISOR);
        $this->actingAs($supervisor);

        // One warm request first, so this measures the query rather than the
        // framework booting.
        $this->getJson('/api/v1/tickets')->assertOk();

        $ms = $this->timeMs('list, first page', fn () => $this->getJson('/api/v1/tickets')->assertOk());

        $this->assertLessThan(
            self::LIST_BUDGET_MS,
            $ms,
            sprintf('The list took %.0fms against %d rows; the budget is %dms.', $ms, self::ROWS, self::LIST_BUDGET_MS),
        );
    }

    public function test_a_filtered_page_is_within_the_budget(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPERVISOR));
        $this->getJson('/api/v1/tickets?status=open')->assertOk();

        $ms = $this->timeMs(
            'list, filtered',
            fn () => $this->getJson('/api/v1/tickets?status=open&priority=urgent')->assertOk(),
        );

        $this->assertLessThan(self::LIST_BUDGET_MS, $ms, sprintf('Filtering took %.0fms.', $ms));
    }

    public function test_full_text_search_is_within_the_budget(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPERVISOR));
        $this->getJson('/api/v1/tickets?q=duplicate')->assertOk();

        $ms = $this->timeMs('full-text search', fn () => $this->getJson('/api/v1/tickets?q=duplicate')->assertOk());

        $this->assertLessThan(
            self::SEARCH_BUDGET_MS,
            $ms,
            sprintf('Search took %.0fms against %d rows; the budget is %dms.', $ms, self::ROWS, self::SEARCH_BUDGET_MS),
        );
    }

    public function test_a_reference_lookup_is_within_the_budget(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPERVISOR));
        $this->getJson('/api/v1/tickets?q=TKT-04')->assertOk();

        $ms = $this->timeMs('reference lookup', fn () => $this->getJson('/api/v1/tickets?q=TKT-04')->assertOk());

        $this->assertLessThan(self::SEARCH_BUDGET_MS, $ms, sprintf('Reference lookup took %.0fms.', $ms));
    }

    public function test_the_counts_strip_is_within_the_budget(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPERVISOR));
        $this->getJson('/api/v1/tickets/counts')->assertOk();

        // The strip loads on every visit to Home and refreshes every thirty
        // seconds; it is the most-run query in the application.
        $ms = $this->timeMs('counts strip', fn () => $this->getJson('/api/v1/tickets/counts')->assertOk());

        $this->assertLessThan(self::LIST_BUDGET_MS, $ms, sprintf('Counts took %.0fms.', $ms));
    }
}
