<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer may not have a department yet.
 *
 * The column was required because every customer used to be typed in by an
 * agent, who picked one. A person who simply emails support has not been
 * assigned to anything — and the honest record of that is an empty field, not
 * whichever department happened to be first in the list.
 *
 * Inventing one would be worse than it sounds: department is what the customer
 * list filters by and what a supervisor reads as "whose book is this on", so a
 * guessed value would put somebody's work in another team's column.
 *
 * The API still REQUIRES it when an agent creates a customer by hand — that is
 * a form rule, not a storage rule, and it stays where it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        /*
         * Not reversible while any customer has a null department: making the
         * column required again would need a value for each of them, and there
         * is no correct one to invent here.
         */
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable(false)->change();
        });
    }
};
