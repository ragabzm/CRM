<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The language a customer chose when they registered.
 *
 * Separate from `customers.preferred_locale`, which records the language the
 * BUSINESS believes they use — inferred from an inbound email, or set by an
 * agent. This one is what the person themselves picked, on a form, and it is
 * what their reset email and every portal screen are rendered in.
 *
 * Keeping both matters when they disagree: an agent who guessed wrong should
 * not override what the customer said about themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_accounts', function (Blueprint $table): void {
            $table->string('preferred_locale', 8)->default('en')->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('portal_accounts', function (Blueprint $table): void {
            $table->dropColumn('preferred_locale');
        });
    }
};
