<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets an internal note actually be saved.
 *
 * `ticket_messages_direction_check` was written in Story 4.1 allowing exactly
 * `inbound` and `outbound`. Story 4.4 added `internal` to the enum, the model,
 * the API and the interface — and never touched the constraint. On Postgres,
 * every internal note has been refused at the database since:
 *
 *   new row for relation "ticket_messages" violates check constraint
 *   "ticket_messages_direction_check"
 *
 * NOTHING CAUGHT IT. The constraint is added under a `pgsql` guard, and the
 * test suite runs on SQLite — so every test about internal notes passed
 * against a table that had no such rule. It surfaced by writing one on a real
 * Postgres.
 *
 * The rebuild is the same shape as `drop_reopened_status`: drop, then re-add
 * with the full set, so the constraint and the enum say the same thing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE ticket_messages DROP CONSTRAINT IF EXISTS ticket_messages_direction_check');

        DB::statement(
            "ALTER TABLE ticket_messages ADD CONSTRAINT ticket_messages_direction_check "
            ."CHECK (direction IN ('inbound','outbound','internal'))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        /*
         * Narrowing again would need every internal note removed first, and
         * there is no correct thing to do with a colleague's note — deleting
         * it destroys the context somebody wrote down, and converting it to
         * outbound would email it to the customer it is about.
         */
        DB::statement('ALTER TABLE ticket_messages DROP CONSTRAINT IF EXISTS ticket_messages_direction_check');
    }
};
