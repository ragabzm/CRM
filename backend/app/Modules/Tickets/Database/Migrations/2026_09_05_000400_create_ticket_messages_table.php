<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What was actually said on a ticket.
 *
 * Append-only, and deliberately NOT version-guarded: two people writing
 * different replies have not conflicted, they have both said something, and
 * both belong in the thread. Guarding this the way the contended attributes are
 * guarded would make a reply fail whenever anyone else touched the ticket —
 * the normal case on a busy ticket.
 *
 * `customer_id` is denormalised from the ticket so a customer's whole
 * interaction history is one indexed read rather than a join through every
 * ticket they have ever had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->restrictOnDelete();

            // Which way it went. The single most important fact about a
            // message after its text.
            $table->string('direction', 16);

            $table->string('author_type', 16);
            $table->string('author_id', 26)->nullable();
            // Denormalised, so the message still says who wrote it after the
            // account is renamed or removed.
            $table->string('author_name', 255);

            $table->text('body');
            $table->timestamp('sent_at');

            $table->timestamps();

            $table->index(['ticket_id', 'sent_at']);
            // The customer timeline's query: everything for one person, newest
            // first. Paired with (id) at read time so entries sharing a second
            // page stably.
            $table->index(['customer_id', 'sent_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ticket_messages ADD CONSTRAINT ticket_messages_direction_check CHECK (direction IN ('inbound','outbound'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
