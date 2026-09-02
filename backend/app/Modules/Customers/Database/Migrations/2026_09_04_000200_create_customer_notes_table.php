<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text notes on a customer.
 *
 * `customer_notes`, not `notes`: a bare `notes` table invites every future
 * module to put its own notes in it, and then the table means nothing in
 * particular. Tickets will want their own, and they will be different.
 *
 * The author is recorded by id with NO foreign key to `users`. A note is a
 * record of what somebody said about a customer, and it has to survive that
 * person leaving — a cascade would erase the note along with the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Cascade: a note has no meaning without its customer. Customers
            // are deactivated rather than deleted, so this fires essentially
            // never — but if a row ever does go, its notes should not outlive it.
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // Denormalised so the note still says who wrote it after the
            // account is renamed or removed.
            $table->string('author_name', 255);

            $table->text('body');

            $table->timestamps();

            // The only read: this customer's notes, newest first.
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
