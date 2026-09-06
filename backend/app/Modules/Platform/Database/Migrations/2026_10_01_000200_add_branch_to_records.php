<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One branch, or none, on the three records that have a place.
 *
 * NULLABLE on all three, and the null is not a gap to be filled later. A desk
 * that has never had more than one office has no branches at all, and every
 * record carrying none is the correct state — which is why an unresolved
 * branch has no consequence anywhere and why branch needs no resolution order
 * of its own. Contrast the department ladder, which exists because an
 * unassigned department DOES have a consequence.
 *
 * `nullOnDelete` rather than cascade: deactivation is the intended path and
 * deletion should not exist, but if a row ever goes, the ticket it described
 * must survive without it. A cascade here would delete tickets to tidy up a
 * label.
 *
 * All three in one migration because they are one decision. Three files would
 * invite a deployment where a customer can have a branch and a ticket cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'customers', 'tickets'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            });
        }

        /*
         * Indexed on tickets only, because that is the only one anybody
         * filters a list by. An index on `users.branch_id` would serve a
         * query nobody runs — the user table is small and read whole.
         */
        Schema::table('tickets', function (Blueprint $table): void {
            $table->index(['branch_id'], 'tickets_branch_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_branch_id_index');
        });

        foreach (['users', 'customers', 'tickets'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
