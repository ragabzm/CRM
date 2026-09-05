<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Modules\Customers\Domain\CustomerSearch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\RunsAgainstRealPostgres;
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
    use RunsAgainstRealPostgres;
    use InteractsWithCustomers;
    use InteractsWithSpaSession;


    protected function setUp(): void
    {
        parent::setUp();

        $this->useRealPostgres('the trigram search path');

        $this->setUpCustomers();
    }

    protected function tearDown(): void
    {
        // Leaves no tables behind in a database someone may also be using by
        // hand. migrate:fresh at setUp would handle it, but only on the next run.
        $this->releaseRealPostgres();

        parent::tearDown();
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
     *
     * The names and addresses vary because the search is a TRIGRAM search. An
     * earlier version of this seeder named every row "Customer <n> Surname<n>"
     * and gave every one of them an address at the same domain, which meant
     * every row was similar enough to every search term to clear the 0.2
     * threshold: a search for one customer matched all 5,000, and the endpoint
     * dutifully ranked and counted all 5,000. That measured sorting an entire
     * table, called it "search on a realistic table", and blamed the search for
     * a shape only the fixture had. `assertSelective` below now holds the
     * fixture to what it claims to be.
     */
    private const GIVEN_NAMES = [
        'Ahmed', 'Layla', 'Yusuf', 'Fatima', 'Omar', 'Nadia', 'Karim', 'Salma',
        'Tariq', 'Hana', 'Ibrahim', 'Mariam', 'Sami', 'Rania', 'Khalid', 'Dina',
        'Hassan', 'Noor', 'Bilal', 'Zeinab', 'Anders', 'Priya', 'Chen', 'Sofia',
        'Marcus', 'Ingrid', 'Diego', 'Aoife', 'Kwame', 'Yuki', 'Lucas', 'Amara',
        'Ravi', 'Elena', 'Tomas', 'Nia', 'Pieter', 'Rosa', 'Jonas', 'Mei',
        'Hugo', 'Zara', 'Felix', 'Iris', 'Adam', 'Leila', 'Victor', 'Maya',
        'Oscar', 'Nour',
    ];

    private const FAMILY_NAMES = [
        'Nakamura', 'Okonkwo', 'Vandenberg', 'Castellanos', 'Fitzgerald',
        'Brennan', 'Kowalski', 'Petrenko', 'Andersson', 'Bjornstad',
        'Rasmussen', 'Lindqvist', 'Moreau', 'Delacroix', 'Rossellini',
        'Marchetti', 'Guzman', 'Villanueva', 'Echeverria', 'Santamaria',
        'Habib', 'Mansour', 'Haddad', 'Chalhoub', 'Zayed',
        'Bakri', 'Sultani', 'Farouk', 'Osman', 'Ghanem',
        'Adeyemi', 'Nwachukwu', 'Mensah', 'Diallo', 'Sankara',
        'Chatterjee', 'Ramaswamy', 'Bhattacharya', 'Krishnan', 'Venkatesan',
        'Yamamoto', 'Watanabe', 'Kobayashi', 'Takahashi', 'Matsumoto',
        'Novotny', 'Horvath', 'Szabolcs', 'Dragomir', 'Stanescu',
        'Whitaker', 'Ashworth', 'Pemberton', 'Kingsley', 'Thornbury',
        'Grimaldi', 'Falconer', 'Ravenscroft', 'Winterbourne', 'Ashcombe',
        'Lindgren', 'Solberg', 'Kristensen', 'Vestergaard', 'Thorvaldsen',
        'Bergqvist', 'Hjelmstad', 'Ostergaard', 'Sigurdsson', 'Halvorsen',
        'Montague', 'Beaumont', 'Carrington', 'Wallingford', 'Fairweather',
        'Hollingsworth', 'Abernathy', 'Crompton', 'Strathmore', 'Kirkpatrick',
        'Quintero', 'Zambrano', 'Barrientos', 'Mondragon', 'Valderrama',
        'Sepulveda', 'Arismendi', 'Bustamante', 'Cifuentes', 'Pizarro',
        'Alperovich', 'Bergmann', 'Dreyfus', 'Eichhorn', 'Fassbinder',
        'Grunewald', 'Hollstein', 'Jungmann', 'Kaltenbach', 'Lindenmayer',
    ];

    private const MAIL_DOMAINS = [
        'northwind.test', 'harborlight.test', 'cedarbrook.test', 'stonegate.test',
        'brightwater.test', 'foxglove.test', 'ironhill.test', 'larkspur.test',
        'meridian.test', 'quarrystone.test', 'redcliff.test', 'silverbirch.test',
        'thistledown.test', 'umberfield.test', 'verdigris.test', 'willowmere.test',
        'ashgrove.test', 'bellhaven.test', 'clearwell.test', 'duskmoor.test',
    ];

    /** The name searched for by the performance test; shared so both agree. */
    private const RARE_FAMILY_NAME = 'Ravenscroft';

    private function seedManyCustomers(): void
    {
        $customers = [];
        $identifiers = [];
        $now = now();

        $given = count(self::GIVEN_NAMES);
        $family = count(self::FAMILY_NAMES);
        $domains = count(self::MAIL_DOMAINS);

        for ($i = 0; $i < self::SEED_ROWS; $i++) {
            $id = (string) \Illuminate\Support\Str::ulid();

            // Every pairing used exactly once: 50 x 100 is 5,000, so no two
            // rows share a full name and a family name recurs 50 times, which
            // is about what a real book of customers looks like.
            $first = self::GIVEN_NAMES[$i % $given];
            $last = self::FAMILY_NAMES[intdiv($i, $given) % $family];
            $fullName = $first.' '.$last;

            $customers[] = [
                'id' => $id,
                'reference' => sprintf('C-%08d', $i),
                'full_name' => $fullName,
                'department_id' => $this->departmentId,
                'state' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $address = strtolower($first.'.'.$last.$i).'@'.self::MAIL_DOMAINS[$i % $domains];

            $identifiers[] = [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'customer_id' => $id,
                'kind' => 'email',
                'value' => $address,
                'value_normalised' => $address,
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

    /** The address seeded for row `$i`, so a test can search for a real one. */
    private function seededAddress(int $i): string
    {
        $first = self::GIVEN_NAMES[$i % count(self::GIVEN_NAMES)];
        $last = self::FAMILY_NAMES[intdiv($i, count(self::GIVEN_NAMES)) % count(self::FAMILY_NAMES)];

        return strtolower($first.'.'.$last.$i).'@'.self::MAIL_DOMAINS[$i % count(self::MAIL_DOMAINS)];
    }

    /**
     * Fails when a search term matches a big share of the table.
     *
     * The ceiling is generous — a family name shared by 50 of 5,000 customers
     * is normal, and trigrams will pull in near misses besides. What it
     * catches is a fixture where the term matches thousands.
     */
    private function assertSelective(string $kind, string $term): void
    {
        $body = json_decode(
            (string) $this->getJson('/api/v1/customers?q='.urlencode($term))->getContent(),
            true,
        );

        $matched = (int) ($body['meta']['total'] ?? 0);

        $this->assertGreaterThan(0, $matched, "Searching by {$kind} for '{$term}' found nothing to measure.");

        $this->assertLessThan(
            (int) (self::SEED_ROWS * 0.1),
            $matched,
            sprintf(
                "Searching by %s for '%s' matched %d of %d rows. The fixture is not realistic, so the timing below would measure sorting the table rather than finding a customer.",
                $kind,
                $term,
                $matched,
                self::SEED_ROWS,
            ),
        );
    }

    /** Milliseconds Postgres itself reported for the last measured request. */
    private float $lastQueryMs = 0.0;

    private function timeSearch(string $term): float
    {
        $this->lastQueryMs = 0.0;

        $started = microtime(true);

        $this->getJson('/api/v1/customers?q='.urlencode($term))->assertOk();

        return (microtime(true) - $started) * 1000;
    }

    public function test_search_meets_the_response_time_target_on_a_realistic_table(): void
    {
        $this->seedManyCustomers();

        $this->assertSame(self::SEED_ROWS, (int) DB::table('customers')->count());

        /*
         * One request first, and its time is thrown away.
         *
         * `timeSearch` measures a whole HTTP round trip through the test
         * kernel, so the FIRST call also pays for everything the container
         * resolves lazily — routes, the settings registry, the guard. That
         * cost is real in the test and absent in production, where the process
         * is already warm and serving its thousandth request.
         *
         * Leaving it in made the first measurement — always `name` — the one
         * that failed, and the failure said "searching by name is slow" about
         * work that had nothing to do with searching. Measured across three
         * runs it swung between 325ms and 742ms while the query itself did not
         * change at all.
         *
         * The budget stays at 300ms and all four paths are still measured.
         */
        $this->timeSearch('warm the kernel');

        /*
         * Each of the four things a search can match, because they take
         * different paths: name and identifier go through the trigram indexes,
         * the reference through a prefix comparison.
         */
        $measurements = [];
        $sql = [];
        $allSamples = [];

        /*
         * Registered once, not per call: `DB::listen` stacks listeners, and one
         * per measurement makes every later query counted several times over.
         *
         * Postgres reports its own time per statement, so a failure can say
         * whether the search itself was slow or the machine was busy around it.
         */
        DB::listen(function ($query): void {
            $this->lastQueryMs += $query->time;
        });

        $terms = [
            'name' => self::RARE_FAMILY_NAME,
            'email' => $this->seededAddress(1234),
            'reference' => 'C-00000012',
            'partial name' => substr(self::RARE_FAMILY_NAME, 0, 6),
        ];

        /*
         * The fixture has to be selective before the timing means anything.
         *
         * A term that matches most of the table turns the endpoint into a sort
         * of the whole table, and the resulting number says nothing about
         * whether searching is fast — see the note on `seedManyCustomers`.
         */
        foreach ($terms as $kind => $term) {
            $this->assertSelective($kind, $term);
        }

        /*
         * The median of three, not a single sample.
         *
         * This measures wall-clock on whatever machine happens to run it, and
         * a developer box shares its cores with everything else that is open.
         * One unlucky sample makes a passing search look like a regression;
         * three make the number mean something, and a search that genuinely
         * got slower is slow in all three.
         */
        foreach ($terms as $kind => $term) {
            $samples = [];
            $sqlSamples = [];

            for ($attempt = 0; $attempt < 3; $attempt++) {
                $samples[] = $this->timeSearch($term);
                $sqlSamples[] = $this->lastQueryMs;
            }

            sort($samples);
            sort($sqlSamples);

            $measurements[$kind] = $samples[1];
            $sql[$kind] = $sqlSamples[1];
            $allSamples[$kind] = $samples;
        }

        foreach ($measurements as $kind => $ms) {
            $this->assertLessThan(
                self::NFR_02_BUDGET_MS,
                $ms,
                sprintf(
                    'Searching by %s took a median %.0fms against %d rows (%.0fms of it SQL); NFR-02 allows %dms. Samples: %s.',
                    $kind,
                    $ms,
                    self::SEED_ROWS,
                    $sql[$kind],
                    self::NFR_02_BUDGET_MS,
                    implode('ms, ', array_map(fn (float $s): string => sprintf('%.0f', $s), $allSamples[$kind])).'ms',
                ),
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

        $this->getJson('/api/v1/customers?q='.self::RARE_FAMILY_NAME)->assertOk();

        /*
         * A handful, not one per row. The correlated sub-select exists so the
         * identifier score is computed in the same statement — fetching
         * identifiers per customer would be the classic N+1 that turns a fast
         * page into a slow one only once real data arrives.
         */
        $this->assertLessThan(10, $queries, "The search issued {$queries} queries.");
    }
}
