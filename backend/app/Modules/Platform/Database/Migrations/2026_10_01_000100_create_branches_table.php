<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which office a record belongs to.
 *
 * A LABEL, and the schema is shaped to keep it one. There is no permission
 * here, no scope, no parent branch and no manager: a branch says where work
 * happened, and the moment it says who may see it, this product has acquired a
 * second permission system beside the one it already has — with its own edge
 * cases, its own way to lock somebody out, and no test suite that knows about
 * either.
 *
 * In PLATFORM (T0) because everything above it may need to point at one:
 * users, customers and tickets each carry a nullable `branch_id`, and a table
 * three tiers reference has to sit below all of them.
 *
 * Deactivation, never deletion. A branch that closed still describes where
 * three years of tickets happened, and deleting it would either orphan them or
 * quietly rewrite history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 120);

            /*
             * The short code, unique and case-insensitively so.
             *
             * It is what appears in a filter chip and a column where the full
             * name would not fit. Two branches spelled `CAI` and `cai` would
             * read as one to every person and as two to the database.
             */
            $table->string('code', 16)->unique();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // The picker: active first, then by name.
            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
