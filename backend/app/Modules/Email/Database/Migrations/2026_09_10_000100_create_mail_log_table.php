<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every attempt to send or receive an email, and what came of it.
 *
 * The question this answers is "did the customer get it?" — asked by an agent
 * whose reply was met with silence, and by an administrator a provider has
 * started throttling. Without a record, the only evidence is the customer's
 * word and the provider's dashboard, and neither is available at the moment
 * somebody needs it.
 *
 * Separate from `ticket_events`: this is about the CHANNEL, not the ticket. A
 * throttled provider affects every ticket at once and belongs in one place a
 * reader can scan, not scattered across a thousand histories.
 *
 * Body is deliberately NOT stored. It already lives on the message, and a
 * second copy in a log with its own retention would outlive the thing it
 * copied — and would put a customer's words somewhere nobody thinks to look
 * when handling a deletion request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_log', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // `outbound` | `inbound`. Story 5.2 writes the other direction.
            $table->string('direction', 16);

            /*
             * Nullable: a test send belongs to no message, and an inbound mail
             * that could not be correlated belongs to no ticket. A foreign key
             * would make the log refuse exactly the events most worth logging.
             */
            $table->string('message_id', 26)->nullable();
            $table->string('ticket_id', 26)->nullable();

            $table->string('provider', 32);
            $table->string('address', 320);
            $table->string('subject', 512)->nullable();

            // `queued` | `sent` | `failed`
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempt')->default(1);

            // How long the provider took. A number that climbs is the first
            // sign of trouble, and it is invisible without this column.
            $table->unsignedInteger('duration_ms')->nullable();

            $table->text('error')->nullable();
            $table->string('provider_code', 64)->nullable();

            $table->timestamp('occurred_at');

            $table->timestamps();

            // The admin log view: newest first, optionally one direction.
            $table->index(['occurred_at']);
            $table->index(['direction', 'occurred_at']);
            // "What happened to THIS reply", from the conversation screen.
            $table->index(['message_id']);
            // The retention sweep.
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_log');
    }
};
