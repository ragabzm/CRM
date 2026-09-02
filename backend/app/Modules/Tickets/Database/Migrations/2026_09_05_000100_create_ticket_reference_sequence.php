<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The sequence that hands out ticket references.
 *
 * A database sequence rather than MAX(reference)+1, because two agents pressing
 * Create at the same moment would both read the same maximum and both mint
 * TKT-000123. `nextval` is atomic and needs no application lock — the one job
 * this has to do correctly is never returning the same number twice.
 *
 * Postgres only. SQLite has no sequences, and the test suite runs on it; there
 * the allocator falls back to a row count, which is safe because those
 * transactions are serial. See TicketReferenceAllocator.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE SEQUENCE IF NOT EXISTS ticket_reference_seq START 1 INCREMENT 1');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP SEQUENCE IF EXISTS ticket_reference_seq');
    }
};
