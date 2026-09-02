<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes `reopened` from what the database will accept.
 *
 * Story 4.1 shipped five statuses; 4.2 replaced the lifecycle with four. A
 * reopened ticket IS open — it needs working and belongs in the open queue —
 * and the fact that it came back lives in the event stream.
 *
 * The constraint is tightened separately from the code because a database that
 * already ran 4.1's migration still permits the value: a check constraint that
 * allows a status the application cannot produce is a door left open for a
 * hand-written UPDATE.
 *
 * Any existing row is migrated to `open`, which is what it meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The rows first, or the tightened constraint would refuse to apply.
        DB::table('tickets')->where('status', 'reopened')->update(['status' => 'open']);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('open','pending','resolved','closed'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('open','pending','resolved','closed','reopened'))");
    }
};
