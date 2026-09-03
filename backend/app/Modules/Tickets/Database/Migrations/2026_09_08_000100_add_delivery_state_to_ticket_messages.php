<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an outbound message actually reached anyone.
 *
 * Until now a reply was written and the screen said "sent" — which was a claim
 * about the database, not about the customer's inbox. An agent whose reply
 * never left had no way to know, and the customer's silence looked like the
 * customer's silence.
 *
 * Nullable, because only outbound messages have a delivery state at all. An
 * inbound message arrived by definition, and an internal note is never sent
 * anywhere. A `default('queued')` would claim otherwise for both.
 *
 * This story writes `queued` and `failed`; Story 5.1 owns the transition to
 * `sent` when the mail pipeline confirms it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table): void {
            $table->string('delivery_state', 16)->nullable()->after('sent_at');
        });

        // Everything already in the table went out before this column existed;
        // marking it `queued` would put every historical reply in the "still
        // waiting" state on every screen.
        DB::table('ticket_messages')
            ->where('direction', 'outbound')
            ->update(['delivery_state' => 'sent']);

        Schema::table('ticket_messages', function (Blueprint $table): void {
            // The retry queue's read: what has not landed, oldest first.
            $table->index(['delivery_state', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table): void {
            $table->dropIndex(['delivery_state', 'sent_at']);
            $table->dropColumn('delivery_state');
        });
    }
};
