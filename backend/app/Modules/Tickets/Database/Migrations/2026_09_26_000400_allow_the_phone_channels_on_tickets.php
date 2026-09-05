<?php

declare(strict_types=1);

use App\Modules\Tickets\Domain\Enum\TicketChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets a ticket say it arrived by WhatsApp or by text.
 *
 * The constraint is rebuilt from the enum's own cases, exactly as Story 7.1's
 * migration did — so this file exists only because the enum grew, and the next
 * channel needs one case and no second edit.
 *
 * It lives in TICKETS, not in Channels, though a channel story added it. The
 * `tickets` table belongs to this module and only this module writes it —
 * `OnlyTicketsModuleMutatesTicketsTest` enforces that, and it caught this file
 * in the wrong place. A migration is a write like any other.
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

        DB::table('tickets')->whereIn('channel', ['whatsapp', 'sms'])->update(['channel' => 'system']);

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_channel_check');
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_channel_check CHECK (channel IN ('agent','portal','email','web_form','system'))");
    }
};
