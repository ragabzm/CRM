<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What makes the ticket list answer in under a second at fifty thousand rows.
 *
 * Three separate reading patterns, three separate index shapes:
 *
 *   Free text over subject and description — a GIN index on a generated
 *   tsvector. Generated rather than maintained by a trigger, so it cannot drift
 *   from the row it describes.
 *
 *   A reference an agent half-remembers ("...4417") — trigram, because the
 *   useful part is usually the tail and no btree can answer a leading wildcard.
 *
 *   The filter combinations the list actually uses — composite btrees, ordered
 *   with the low-cardinality column first so one index serves both "status" and
 *   "status + assignee".
 *
 * Postgres only. SQLite has no tsvector, no pg_trgm and no generated-column
 * index support worth having, and the query object falls back to LIKE there —
 * see TicketListQuery. The test suite runs on SQLite, which is exactly why
 * TicketSearchPostgresTest exists and skips loudly rather than silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        /*
         * 'simple' rather than 'english': the content is bilingual, and the
         * English stemmer would mangle Arabic while helping nothing. Language
         * neutrality beats slightly better English stemming on a corpus that is
         * half Arabic.
         */
        if (! Schema::hasColumn('tickets', 'search_vector')) {
            DB::statement(<<<'SQL'
                ALTER TABLE tickets
                ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    to_tsvector('simple',
                        coalesce(reference, '') || ' ' ||
                        coalesce(subject, '') || ' ' ||
                        coalesce(description, '')
                    )
                ) STORED
            SQL);
        }

        foreach ([
            'CREATE INDEX IF NOT EXISTS tickets_search_vector_idx ON tickets USING gin (search_vector)',
            'CREATE INDEX IF NOT EXISTS tickets_reference_trgm_idx ON tickets USING gin (reference gin_trgm_ops)',

            // Ordered low-cardinality first, so each also serves a query that
            // filters on the leading column alone.
            'CREATE INDEX IF NOT EXISTS tickets_status_assignee_idx ON tickets (status, assignee_id)',
            'CREATE INDEX IF NOT EXISTS tickets_status_priority_idx ON tickets (status, priority)',
            'CREATE INDEX IF NOT EXISTS tickets_department_status_idx ON tickets (department_id, status)',

            // The list's default order, so the first page needs no sort at all.
            'CREATE INDEX IF NOT EXISTS tickets_updated_at_idx ON tickets (updated_at DESC)',
        ] as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'tickets_search_vector_idx',
            'tickets_reference_trgm_idx',
            'tickets_status_assignee_idx',
            'tickets_status_priority_idx',
            'tickets_department_status_idx',
            'tickets_updated_at_idx',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        if (Schema::hasColumn('tickets', 'search_vector')) {
            DB::statement('ALTER TABLE tickets DROP COLUMN search_vector');
        }

        // pg_trgm is deliberately left installed: customers search uses it too,
        // and dropping a shared extension on a ticket rollback would break an
        // unrelated feature.
    }
};
