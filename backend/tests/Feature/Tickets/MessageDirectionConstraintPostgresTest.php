<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Tickets\Domain\Enum\MessageDirection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The database agrees with the enum about what a direction can be.
 *
 * This test exists because they disagreed for two stories and nothing noticed.
 * `ticket_messages_direction_check` was written in Story 4.1 allowing
 * `inbound` and `outbound`; Story 4.4 added `internal` everywhere EXCEPT the
 * constraint, and every internal note was refused at the database.
 *
 * The suite runs on SQLite, and the constraint is added under a `pgsql` guard —
 * so every test about internal notes passed against a table with no such rule.
 * A guard that only exists on the driver the tests do not use is a guard no
 * test can check, which is exactly why this file talks to a real Postgres.
 */
final class MessageDirectionConstraintPostgresTest extends TestCase
{
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
                'Postgres unreachable — the direction constraint was NOT checked. Reason: '.$e->getMessage(),
            );
        }

        Config::set('database.default', 'pgsql');

        foreach ($settings as $key => $value) {
            Config::set("database.connections.pgsql.{$key}", $value);
        }

        DB::purge('pgsql');
        Artisan::call('migrate:fresh', ['--database' => 'pgsql', '--force' => true]);
    }

    protected function tearDown(): void
    {
        if (Config::get('database.default') === 'pgsql') {
            Artisan::call('migrate:reset', ['--database' => 'pgsql', '--force' => true]);
        }

        parent::tearDown();
    }

    public function test_the_constraint_accepts_every_direction_the_enum_declares(): void
    {
        $allowed = (string) DB::table('pg_constraint')
            ->whereRaw("conname = 'ticket_messages_direction_check'")
            ->selectRaw('pg_get_constraintdef(oid) as def')
            ->value('def');

        $this->assertNotSame('', $allowed, 'The direction constraint is missing entirely.');

        /*
         * Every case, checked against the constraint text. Adding a fifth
         * direction without a migration would fail here rather than in
         * production on the first message that used it.
         */
        foreach (MessageDirection::cases() as $direction) {
            $this->assertStringContainsString(
                "'{$direction->value}'",
                $allowed,
                "The database refuses [{$direction->value}], which the enum allows.",
            );
        }
    }

    public function test_the_constraint_still_refuses_something_invented(): void
    {
        $allowed = (string) DB::table('pg_constraint')
            ->whereRaw("conname = 'ticket_messages_direction_check'")
            ->selectRaw('pg_get_constraintdef(oid) as def')
            ->value('def');

        // The rule above passes trivially if the constraint were widened to
        // allow anything.
        $this->assertStringNotContainsString("'sideways'", $allowed);
    }
}
