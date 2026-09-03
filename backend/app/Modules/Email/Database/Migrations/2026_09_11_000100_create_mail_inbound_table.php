<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per email the provider has handed us, ever.
 *
 * THE IDEMPOTENCY RECORD. Providers retry — that is the contract, not a fault:
 * a webhook that times out after we committed will be delivered again, and a
 * provider having a bad afternoon may deliver the same message five times. With
 * no record, each delivery becomes another ticket, and a customer who wrote once
 * finds five identical requests with five different references.
 *
 * The unique key is claimed BEFORE anything is parsed. Parsing can fail, and a
 * message that failed to parse must not be retried into a duplicate the moment
 * the parser is fixed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_inbound', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            /*
             * The provider's own id where it gives one, else the MIME
             * Message-ID. Unique, and that constraint is the whole mechanism —
             * a second delivery loses the insert rather than being detected by
             * a check-then-act race between two workers.
             */
            $table->string('external_id', 512)->unique();

            $table->string('provider', 32);

            // `received` | `correlated` | `quarantined`
            $table->string('status', 16);

            $table->string('from_address', 320)->nullable();
            $table->string('subject', 512)->nullable();

            $table->string('ticket_id', 26)->nullable();
            $table->string('message_id', 26)->nullable();
            $table->string('customer_id', 26)->nullable();

            /*
             * Which rule matched, and what the others saw.
             *
             * "Why did this land on that ticket?" is the question every
             * mis-correlated email raises, and without the trace the only
             * answer is to re-run the logic against a message that has since
             * been consumed.
             */
            $table->json('correlation_trace')->nullable();

            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index(['ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_inbound');
    }
};
