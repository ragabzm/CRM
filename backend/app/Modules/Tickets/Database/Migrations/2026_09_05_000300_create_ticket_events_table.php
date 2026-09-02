<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a ticket, in order.
 *
 * Append-only, like the audit log — but a different table on purpose. The audit
 * log answers "who did what across the whole system" for a security review;
 * this answers "what happened to THIS ticket" and is read by agents, in the
 * ticket, all day. Merging them would put every sign-in attempt in a customer's
 * ticket history.
 *
 * No `updated_at`: an event records something that already happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Cascade: an event has no meaning without its ticket.
            $table->foreignUlid('ticket_id')->constrained('tickets')->cascadeOnDelete();

            $table->string('event_type', 48);
            $table->json('payload')->nullable();

            $table->string('actor_type', 16);
            $table->string('actor_id', 26)->nullable();
            // Why the system acted, when there is no person to name.
            $table->string('actor_reason', 120)->nullable();

            /*
             * The version the ticket held AFTER this event. Replaying the
             * events reproduces the version history exactly, which is what
             * makes a disputed change reconstructable.
             */
            $table->unsignedInteger('version_after');

            $table->timestamp('created_at');

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};
