<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Modules\Customers\Domain\CustomerSearch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * The search path production actually runs.
 *
 * The rest of the suite runs on SQLite and exercises the portable containment
 * fallback. That is a genuinely different implementation — trigram similarity
 * tolerates typos and ranks results, containment does neither — so testing only
 * the fallback would mean the production query has never been executed by
 * anything but a human clicking around.
 *
 * Skips when no Postgres is reachable, so the suite still runs anywhere. It is
 * a skip rather than a silent pass: the reason is printed, so nobody concludes
 * from green output that the trigram path was covered.
 */
final class CustomerSearchPostgresTest extends TestCase
{
    use InteractsWithCustomers;
    use InteractsWithSpaSession;

    /**
     * Where to find a Postgres for this suite.
     *
     * Read from DB_TEST_* rather than DB_*, because phpunit.xml deliberately
     * overrides the ordinary connection to in-memory SQLite for every other
     * test — reusing those values here would point the DSN at ":memory:".
     */
    private function postgresSettings(): array
    {
        return [
            'host' => (string) env('DB_TEST_HOST', '127.0.0.1'),
            'port' => (int) env('DB_TEST_PORT', 5432),
            'database' => (string) env('DB_TEST_DATABASE', 'ragab_test'),
            'username' => (string) env('DB_TEST_USERNAME', 'ragab'),
            'password' => (string) env('DB_TEST_PASSWORD', 'ragab'),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $settings = $this->postgresSettings();
        $this->skipUnlessPostgresIsReachable($settings);

        Config::set('database.default', 'pgsql');

        foreach ($settings as $key => $value) {
            Config::set("database.connections.pgsql.{$key}", $value);
        }

        DB::purge('pgsql');
        Artisan::call('migrate:fresh', ['--database' => 'pgsql', '--force' => true]);

        $this->setUpCustomers();
    }

    protected function tearDown(): void
    {
        // Leaves no tables behind in a database someone may also be using by
        // hand. migrate:fresh at setUp would handle it, but only on the next run.
        if (Config::get('database.default') === 'pgsql') {
            Artisan::call('migrate:reset', ['--database' => 'pgsql', '--force' => true]);
        }

        parent::tearDown();
    }

    /**
     * @param  array{host:string,port:int,database:string,username:string,password:string}  $settings
     */
    private function skipUnlessPostgresIsReachable(array $settings): void
    {
        try {
            new \PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $settings['host'], $settings['port'], $settings['database']),
                $settings['username'],
                $settings['password'],
                [\PDO::ATTR_TIMEOUT => 2],
            );
        } catch (\Throwable $e) {
            // A skip, never a silent pass: nobody should read green output and
            // conclude the trigram path was covered.
            $this->markTestSkipped(
                'Postgres unreachable — the trigram search path was NOT covered. '.
                'Create the database and set DB_TEST_* to run it. Reason: '.$e->getMessage(),
            );
        }
    }

    /** @return list<string> */
    private function search(string $query): array
    {
        $body = $this->getJson('/api/v1/customers?q='.urlencode($query))->assertOk()->json();

        return array_column($body['data'], 'full_name');
    }

    public function test_the_production_path_really_is_the_trigram_one(): void
    {
        // Guards the guard: if this ever reports false, every assertion below
        // is silently testing the fallback again.
        $this->assertTrue($this->app->make(CustomerSearch::class)->usesTrigrams());
    }

    public function test_the_trigram_index_exists(): void
    {
        $indexes = DB::select("select indexname from pg_indexes where tablename in ('customers','contact_identifiers')");
        $names = array_column($indexes, 'indexname');

        // Without these the similarity scan reads the whole table, which is
        // fine at a thousand rows and not at a hundred thousand.
        $this->assertContains('customers_full_name_trgm', $names);
        $this->assertContains('contact_identifiers_value_trgm', $names);
    }

    public function test_it_finds_a_name_despite_a_typo(): void
    {
        $this->createCustomer('Hana Yousef', [['kind' => 'email', 'value' => 'hana@example.test']]);

        // The whole reason for trigrams: an agent hears a name once, over a
        // phone line, and types it approximately.
        $this->assertContains('Hana Yousef', $this->search('Hanna Yousef'));
        $this->assertContains('Hana Yousef', $this->search('Yusef'));
    }

    public function test_it_finds_an_arabic_name(): void
    {
        $this->createCustomer('نور الهدى', [['kind' => 'email', 'value' => 'noor@example.test']]);

        $this->assertContains('نور الهدى', $this->search('نور'));
    }

    public function test_it_finds_a_customer_by_a_partial_phone(): void
    {
        $this->createCustomer('Hana Yousef', [['kind' => 'phone', 'value' => '+44 20 7946 0958']]);

        $this->assertContains('Hana Yousef', $this->search('020 7946 0958'));
    }

    public function test_a_reference_prefix_is_matched_exactly_not_by_similarity(): void
    {
        $customer = $this->createCustomer('Hana Yousef', [['kind' => 'email', 'value' => 'hana@example.test']]);

        // Similarity on an eight-character code produces nonsense matches, so
        // the reference is a prefix comparison instead.
        $this->assertContains('Hana Yousef', $this->search(substr($customer['reference'], 0, 5)));
    }

    public function test_a_customer_with_many_identifiers_appears_once(): void
    {
        $this->createCustomer('Many Ways', [
            ['kind' => 'email', 'value' => 'many@example.test'],
            ['kind' => 'email', 'value' => 'many.ways@example.test'],
            ['kind' => 'email', 'value' => 'm.ways@example.test'],
        ]);

        // The correlated sub-select exists for this: a join would return the
        // row three times and break both the count and the paging.
        $this->assertSame(['Many Ways'], $this->search('many'));
        $this->assertSame(1, $this->getJson('/api/v1/customers?q=many')->json('meta.total'));
    }

    public function test_the_state_check_constraint_is_enforced_by_the_database(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Not just the enum in PHP: the column itself cannot hold a bad value,
        // which survives a console command written in a hurry.
        DB::table('customers')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'reference' => 'C-BADSTATE',
            'full_name' => 'Bad State',
            'department_id' => $this->departmentId,
            'state' => 'deleted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** NFR-02: a search must come back inside this, on a realistic table. */
    private const NFR_02_BUDGET_MS = 300;

    private const SEED_ROWS = 5000;

    /**
     * Bulk-inserts a realistic table.
     *
     * Raw inserts rather than the API: this measures the SEARCH, and paying for
     * 5,000 HTTP round trips first would make the test take minutes and prove
     * nothing extra.
     */
    private function seedManyCustomers(): void
    {
        $customers = [];
        $identifiers = [];
        $now = now();

        for ($i = 0; $i < self::SEED_ROWS; $i++) {
            $id = (string) \Illuminate\Support\Str::ulid();

            $customers[] = [
                'id' => $id,
                'reference' => sprintf('C-%08d', $i),
                'full_name' => 'Customer '.$i.' Surname'.($i % 97),
                'department_id' => $this->departmentId,
                'state' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $identifiers[] = [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'customer_id' => $id,
                'kind' => 'email',
                'value' => "person{$i}@example.test",
                'value_normalised' => "person{$i}@example.test",
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($customers, 500) as $chunk) {
            DB::table('customers')->insert($chunk);
        }

        foreach (array_chunk($identifiers, 500) as $chunk) {
            DB::table('contact_identifiers')->insert($chunk);
        }

        // Trigram indexes are only chosen once the planner has statistics.
        DB::statement('ANALYZE customers');
        DB::statement('ANALYZE contact_identifiers');
    }

    private function timeSearch(string $term): float
    {
        $started = microtime(true);

        $this->getJson('/api/v1/customers?q='.urlencode($term))->assertOk();

        return (microtime(true) - $started) * 1000;
    }

    public function test_search_meets_the_response_time_target_on_a_realistic_table(): void
    {
        $this->seedManyCustomers();

        $this->assertSame(self::SEED_ROWS, (int) DB::table('customers')->count());

        /*
         * Each of the four things a search can match, because they take
         * different paths: name and identifier go through the trigram indexes,
         * the reference through a prefix comparison.
         */
        $measurements = [
            'name' => $this->timeSearch('Surname42'),
            'email' => $this->timeSearch('person1234@example.test'),
            'reference' => $this->timeSearch('C-000012'),
            'partial name' => $this->timeSearch('Customer 99'),
        ];

        foreach ($measurements as $kind => $ms) {
            $this->assertLessThan(
                self::NFR_02_BUDGET_MS,
                $ms,
                sprintf('Searching by %s took %.0fms against %d rows; NFR-02 allows %dms.',
                    $kind, $ms, self::SEED_ROWS, self::NFR_02_BUDGET_MS),
            );
        }
    }

    public function test_the_search_stays_a_single_query_however_many_identifiers_match(): void
    {
        $this->seedManyCustomers();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->getJson('/api/v1/customers?q=Surname42')->assertOk();

        /*
         * A handful, not one per row. The correlated sub-select exists so the
         * identifier score is computed in the same statement — fetching
         * identifiers per customer would be the classic N+1 that turns a fast
         * page into a slow one only once real data arrives.
         */
        $this->assertLessThan(10, $queries, "The search issued {$queries} queries.");
    }
}
