<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Did it go well? Yes, no, or nobody said.
 *
 * A NULLABLE BOOLEAN, and the nullability is the third state rather than a
 * gap. An unrated ticket is not neutral and it is not zero — it is a ticket
 * whose customer did not answer, and any figure that treated it as a middling
 * score would be inventing an opinion nobody expressed.
 *
 * Deliberately not an integer "for flexibility". An integer is a scale; a
 * scale needs a range, a midpoint, a label per point and a conversion the
 * first time somebody wants to compare two periods that used different ones.
 * The product refuses all of that on purpose (§19.1, FR-101), and the column
 * type is where that refusal is cheapest to hold: nobody can quietly store a 3
 * in a boolean.
 *
 * `satisfaction_at` is when they said it, so the change window has something
 * to measure against and the history has a timestamp that is not the ticket's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->boolean('satisfaction')->nullable()->after('escalation_reason');
            $table->string('satisfaction_comment', 2000)->nullable()->after('satisfaction');
            $table->timestamp('satisfaction_at')->nullable()->after('satisfaction_comment');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            /*
             * Story 11.2 reports the positive rate over a period, which is
             * "how many true, how many false, in this date range" — and this
             * is the index that answers it without a scan.
             */
            $table->index(['satisfaction', 'satisfaction_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['satisfaction', 'satisfaction_at']);
            $table->dropColumn(['satisfaction', 'satisfaction_comment', 'satisfaction_at']);
        });
    }
};
