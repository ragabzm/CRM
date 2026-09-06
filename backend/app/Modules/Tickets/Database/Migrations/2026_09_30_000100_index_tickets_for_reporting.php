<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The indexes the report queries actually run against.
 *
 * A month-range aggregate over fifty thousand tickets is not free, and the
 * reports are read by somebody who asked a question and is waiting for the
 * answer. Without these, every card on the surface is a sequential scan of the
 * whole table — six of them, plus three breakdowns.
 *
 * In TICKETS, not in Reporting, though a reporting story added them: this
 * table belongs to this module. Reporting owns no table and writes nothing,
 * and that includes its schema.
 *
 * Named for the query rather than the column, so the next person adding a
 * filter can see which ones already have somewhere to go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            // "How many arrived in this period", and the volume breakdowns
            // that all start from the same range.
            $table->index(['created_at'], 'tickets_created_at_index');

            // "How many were resolved in this period" — a different question
            // from how many arrived, and the one a supervisor asks second.
            $table->index(['resolved_at'], 'tickets_resolved_at_index');

            /*
             * The six cards. Status leads because the cards are five counts of
             * one status and a total; a range-first index would make each card
             * re-scan the period.
             */
            $table->index(['status', 'created_at'], 'tickets_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_created_at_index');
            $table->dropIndex('tickets_resolved_at_index');
            $table->dropIndex('tickets_status_created_at_index');
        });
    }
};
