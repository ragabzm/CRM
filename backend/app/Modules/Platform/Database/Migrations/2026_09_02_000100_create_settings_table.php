<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator-changeable values.
 *
 * Only keys DECLARED in a module's registry may be written, so this table is a
 * store rather than a dumping ground — the rules live in code where they can be
 * reviewed, and the table holds only what those rules permit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            // Dotted `module.key`. 191 to stay inside the MySQL index limit if
            // this schema is ever run there.
            $table->string('key', 191)->primary();

            // Recorded as well as declared: the column says what was written,
            // the definition says what is allowed. On disagreement the
            // definition wins and the value falls back to its default.
            $table->string('type', 32);

            // JSON for every type, so one column round-trips a bool, an int and
            // a nested array without per-type columns or string parsing.
            $table->json('value')->nullable();

            // Nullable and nullOnDelete: a setting outlives the administrator
            // who set it, and deactivating them must not erase the value.
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
