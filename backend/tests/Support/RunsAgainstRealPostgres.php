<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Points one test class at a real Postgres, or says out loud that it did not.
 *
 * `phpunit.xml` runs everything on in-memory SQLite, which is what makes the
 * suite fast and portable. It is also what hides every rule Postgres enforces
 * and SQLite does not — the CHECK constraints most of all. A class that needs
 * the real thing takes it from `DB_TEST_*`, and SKIPS with a reason when it is
 * not there rather than passing quietly.
 *
 * Extracted from `CustomerSearchPostgresTest` and `TicketSearchPostgresTest`,
 * which had grown identical copies of it.
 */
trait RunsAgainstRealPostgres
{
    /**
     * Where to find a Postgres for this suite.
     *
     * `DB_TEST_*` rather than `DB_*`: phpunit.xml deliberately overrides the
     * ordinary connection to ":memory:", and reusing those values here would
     * point the DSN at a file that is not a database.
     *
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    protected function postgresSettings(): array
    {
        return [
            'host' => (string) env('DB_TEST_HOST', '127.0.0.1'),
            'port' => (int) env('DB_TEST_PORT', 5432),
            'database' => (string) env('DB_TEST_DATABASE', 'ragab_test'),
            'username' => (string) env('DB_TEST_USERNAME', 'ragab'),
            'password' => (string) env('DB_TEST_PASSWORD', 'ragab'),
        ];
    }

    /**
     * @param  array{host: string, port: int, database: string, username: string, password: string}  $settings
     */
    protected function skipUnlessPostgresIsReachable(array $settings, string $whatIsUncovered): void
    {
        try {
            new PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $settings['host'], $settings['port'], $settings['database']),
                $settings['username'],
                $settings['password'],
                [PDO::ATTR_TIMEOUT => 2],
            );
        } catch (Throwable $e) {
            $this->markTestSkipped(
                "Postgres unreachable — {$whatIsUncovered} was NOT covered. "
                .'Create the database and set DB_TEST_* to run it. Reason: '.$e->getMessage(),
            );
        }
    }

    /** Switches the default connection to the real Postgres and rebuilds it. */
    protected function useRealPostgres(string $whatIsUncovered): void
    {
        $settings = $this->postgresSettings();
        $this->skipUnlessPostgresIsReachable($settings, $whatIsUncovered);

        Config::set('database.default', 'pgsql');

        foreach ($settings as $key => $value) {
            Config::set("database.connections.pgsql.{$key}", $value);
        }

        DB::purge('pgsql');
        Artisan::call('migrate:fresh', ['--database' => 'pgsql', '--force' => true]);
    }

    /**
     * Leaves no tables behind in a database someone may also be using by hand.
     *
     * `migrate:fresh` at setUp would handle it too, but only on the next run.
     */
    protected function releaseRealPostgres(): void
    {
        if (Config::get('database.default') === 'pgsql') {
            Artisan::call('migrate:reset', ['--database' => 'pgsql', '--force' => true]);
        }
    }
}
