<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets a ticket say it came from the public form.
 *
 * The CHECK on `tickets.channel` was written when there were four channels, and
 * it is pgsql-only — which means the test suite on SQLite would never have
 * noticed a fifth one being refused. Exactly the shape of bug that has bitten
 * this schema before: a constraint that only production enforces, and a value
 * the application is happy to write.
 *
 * Rebuilt from the enum's own cases rather than a hand-typed list, so the next
 * channel is one line in one file.
 */
return new class extends Migration
{
    /** Every value `TicketChannel` allows, quoted for SQL. */
    private function allowed(): string
    {
        return implode(',', array_map(
            static fn (\App\Modules\Tickets\Domain\Enum\TicketChannel $case): string => "'".$case->value."'",
            \App\Modules\Tickets\Domain\Enum\TicketChannel::cases(),
        ));
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_channel_check');
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT tickets_channel_check CHECK (channel IN ('.$this->allowed().'))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_channel_check');
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_channel_check CHECK (channel IN ('agent','portal','email','system'))");
    }
};
