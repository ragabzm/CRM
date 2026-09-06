<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every call this product makes to somebody else's system.
 *
 * ONE log, not one per integration. The question it answers is asked in a
 * hurry — "the sync stopped last night, what happened?" — and a reader who has
 * to know which of four tables to look in has already lost. This generalises
 * the mail log Story 5.1 built: same shape, same purpose, one place.
 *
 * It NEVER HOLDS A CREDENTIAL. Redaction happens where the row is written,
 * against the configured secret values and a header deny-list — not by a
 * reviewer remembering to omit them, because reviewers forget and this table
 * is read by more people than the code that wrote it.
 *
 * Deletion is by RETENTION and by nothing else. There is no delete endpoint,
 * no admin button and no ad-hoc cleanup: a log somebody can tidy is a log that
 * gets tidied the morning after the thing worth explaining.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_exchanges', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            /** `outbound` | `inbound`. */
            $table->string('direction', 16);

            /*
             * Which integration, as a name rather than a foreign key. The
             * configuration lives in settings and can be renamed or removed;
             * a log row has to keep meaning something after either.
             */
            $table->string('integration', 64);

            /** The endpoint or address reached. Never a credential in a query. */
            $table->string('target', 512);

            /** `queued` | `succeeded` | `failed`. */
            $table->string('status', 16);

            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('response_status')->nullable();

            /*
             * How long the other end took. A number that climbs is the first
             * sign of trouble, and it is invisible without this column.
             */
            $table->unsignedInteger('duration_ms')->nullable();

            /*
             * The request and response, ALREADY REDACTED. Stored because a
             * failed exchange is undiagnosable without them — "it returned
             * 400" is not an answer anybody can act on.
             */
            $table->text('request')->nullable();
            $table->text('response')->nullable();

            $table->text('error')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            // The admin log view: newest first, optionally one integration.
            $table->index(['occurred_at']);
            $table->index(['integration', 'occurred_at']);
            // The retention sweep, and "what is failing" on the same index.
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_exchanges');
    }
};
