<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere for an anonymous upload to belong before the ticket exists.
 *
 * A person attaching a screenshot to the public form has no account, and the
 * ticket that will own the file is created by the submission the file is part
 * of. The token issued when the page is drawn is what stands between "an
 * upload endpoint for the form" and "a public file store".
 *
 * Short-lived on purpose: a token that never expires is a permanent write
 * credential handed to anybody who loads the page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_form_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->timestamp('expires_at');
            $table->timestamps();

            // The sweep that clears the expired ones reads this.
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_form_sessions');
    }
};
