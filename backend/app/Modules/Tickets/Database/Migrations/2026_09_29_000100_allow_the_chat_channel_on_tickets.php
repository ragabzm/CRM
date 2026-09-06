<?php

declare(strict_types=1);

use App\Modules\Tickets\Domain\Enum\TicketChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets a ticket say it arrived through the chat widget.
 *
 * Rebuilt from the enum's own cases, like every widening before it, so the
 * next channel needs one case on the enum and one file that is a copy of this
 * one. `EnumBackedCheckConstraintsTest` fails if the two ever disagree — that
 * guard exists because this exact constraint fell behind its enum twice before
 * anybody noticed, both times invisibly, because the test suite runs on SQLite
 * and only production enforces it.
 *
 * In TICKETS, not in Channels, though a channel story added it: the `tickets`
 * table belongs to this module and only this module writes it. A migration is
 * a write like any other.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = implode(',', array_map(
            static fn (TicketChannel $case): string => "'".$case->value."'",
            TicketChannel::cases(),
        ));

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_channel_check');
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_channel_check CHECK (channel IN ({$allowed}))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('tickets')->where('channel', 'chat')->update(['channel' => 'system']);

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_channel_check');
        DB::statement(
            "ALTER TABLE tickets ADD CONSTRAINT tickets_channel_check ".
            "CHECK (channel IN ('agent','portal','email','web_form','whatsapp','sms','system'))"
        );
    }
};
