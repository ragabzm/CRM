<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mail we could not turn into anything.
 *
 * A customer who emails support and hears nothing back has been ignored,
 * whatever the technical reason. Silently dropping an unparseable message means
 * nobody ever learns it happened — not the customer, who waits, and not the
 * administrator, who has no reason to look.
 *
 * The RAW SOURCE is kept, in full. A parser bug is only diagnosable against the
 * bytes that broke it, and replaying a quarantined message once the parser is
 * fixed is the entire point of keeping it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_quarantine', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('external_id', 512)->nullable();
            $table->string('provider', 32);

            // Best-effort: these come from whatever could be read before the
            // parse gave up, so all three are nullable by necessity.
            $table->string('from_address', 320)->nullable();
            $table->string('subject', 512)->nullable();

            /** Why it could not be handled, in the parser's own words. */
            $table->text('reason');

            /*
             * The bytes as they arrived. `longText` because a message with
             * attachments legitimately runs to megabytes, and truncating the
             * evidence defeats the purpose of keeping it.
             */
            $table->longText('raw');

            // Set when an administrator has replayed or dismissed it, so the
            // list shows what still needs a human.
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 26)->nullable();

            $table->timestamp('received_at');
            $table->timestamps();

            // The admin list: outstanding first.
            $table->index(['resolved_at', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_quarantine');
    }
};
