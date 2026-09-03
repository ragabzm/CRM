<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How much is actually in there.
 *
 * Written for the end of `scripts/restart.sh`, where the useful question after
 * a rebuild is not "did the command exit 0" but "is there anything to look
 * at". A seed that silently wrote nothing exits 0 and prints success, and the
 * first sign of trouble is a developer signing in to twelve empty screens.
 *
 * Read-only, by construction: it counts rows and does nothing else, so it is
 * safe to run anywhere, including against production when somebody is trying
 * to work out what a deployment did.
 */
final class DatabaseCountsCommand extends Command
{
    protected $signature = 'db:counts';

    protected $description = 'Row counts for the tables a rebuild is expected to fill';

    /**
     * The tables worth reporting, in the order a person reads them: who works
     * here, then who they work for, then the work itself.
     *
     * @var list<string>
     */
    private const TABLES = [
        'departments',
        'users',
        'ticket_categories',
        'customers',
        'contact_identifiers',
        'customer_notes',
        'portal_accounts',
        'tickets',
        'ticket_messages',
        'ticket_events',
        'attachments',
    ];

    public function handle(): int
    {
        $rows = [];

        foreach (self::TABLES as $table) {
            /*
             * Skipped rather than fatal. This runs right after a migration,
             * and a table that does not exist yet is information — not a
             * reason to fail a rebuild that otherwise worked.
             */
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows[] = [$table, DB::table($table)->count()];
        }

        $this->table(['table', 'rows'], $rows);

        $empty = array_column(array_filter($rows, static fn (array $row): bool => $row[1] === 0), 0);

        if ($empty !== []) {
            $this->warn('Empty: '.implode(', ', $empty));
        }

        return self::SUCCESS;
    }
}
