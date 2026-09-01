<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The portal identity space.
 *
 * A second table rather than a flag on `users`: see the note on PortalAccount.
 * Its own reset-token table too — a token minted for a customer must not be
 * redeemable against a staff account, and separate tables make that structural
 * rather than a WHERE clause someone can forget.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('portal_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            // Hashed at rest by Laravel's broker; the plaintext exists only in
            // the emailed link.
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_password_reset_tokens');
        Schema::dropIfExists('portal_accounts');
    }
};
