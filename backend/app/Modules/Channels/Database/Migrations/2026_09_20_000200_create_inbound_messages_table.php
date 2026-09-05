<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every inbound message on every channel, in one table.
 *
 * `mail_inbound` was this table for one transport. Keeping a second copy per
 * channel would mean the idempotency rule, the correlation trace and the
 * delivery states drift apart one channel at a time, and "has this message
 * been seen?" becomes a question you have to ask five tables.
 *
 * `unique(channel, provider_message_id)` is the whole mechanism. A second
 * delivery loses the insert rather than being detected by a check-then-act race
 * between two workers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('channel', 32);

            /*
             * The provider's own id where it gives one, else the transport's
             * natural id (a MIME Message-ID), else a hash of the payload.
             */
            $table->string('provider_message_id', 512);

            $table->string('channel_account_id', 26)->nullable();

            $table->string('ticket_id', 26)->nullable();
            $table->string('message_id', 26)->nullable();
            $table->string('customer_id', 26)->nullable();

            $table->string('direction', 16)->default('inbound');

            // Who it came from and who it was addressed to, in whatever form
            // the channel names people: an address, a number, a session.
            $table->string('sender_identifier', 320);
            $table->string('recipient_identifier', 320)->nullable();

            $table->string('subject', 512)->nullable();
            $table->longText('body')->nullable();

            // Transport-specific facts. Mail headers live here rather than as
            // eight nullable columns nothing else will ever use.
            $table->json('headers')->nullable();
            $table->json('raw_payload')->nullable();

            /** Which correlation rule matched: the short answer. */
            $table->string('correlation_reason', 32)->nullable();

            /** Which department rule matched: `agent` | `channel` | `customer` | `default`. */
            $table->string('department_rule', 16)->nullable();

            /*
             * Which rule matched, and what the others saw.
             *
             * "Why did this land on that ticket?" is the question every
             * mis-correlated message raises, and without the trace the only
             * answer is to re-run the logic against a message that has since
             * been consumed.
             */
            $table->json('correlation_trace')->nullable();

            // `received` | `correlated` | `quarantined`
            $table->string('delivery_state', 32)->default('received');

            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['channel', 'provider_message_id']);
            $table->index(['channel', 'sender_identifier', 'received_at']);
            $table->index(['delivery_state', 'received_at']);
            $table->index(['ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_messages');
    }
};
