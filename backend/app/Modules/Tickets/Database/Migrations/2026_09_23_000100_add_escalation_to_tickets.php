<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ticket that is going wrong, marked as such.
 *
 * Three nullable columns and NO `escalation_level`. A level implies a ladder,
 * a ladder implies rules about who is on which rung and what moves a ticket
 * between them, and there are no rules here — there is one manual action and
 * one automatic condition. The column would be the first half of a workflow
 * engine, added before anybody asked for one.
 *
 * Escalation is a PROPERTY, not a status. `status` stays exactly Open ·
 * Pending · Resolved · Closed, and an escalated ticket keeps whichever of
 * those it had. Adding an `escalated` status would have meant every filter,
 * every count and every transition rule learning a fifth value that is not
 * really a lifecycle state at all.
 *
 * `escalation_reason` is NOT NULL at the application boundary rather than in
 * the schema, because the column has to be nullable for the tickets that are
 * not escalated. The command refuses an empty reason — see `EscalateTicket`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('escalated_at')->nullable()->after('resolved_at');

            /*
             * A string, not a foreign key to users. The escalator may be the
             * system, which has no row — and a user who leaves must not take
             * the record of what they did with them.
             */
            $table->string('escalated_by', 26)->nullable()->after('escalated_at');

            $table->string('escalation_reason', 500)->nullable()->after('escalated_by');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            /*
             * The list filter reads this: "escalated tickets, newest first".
             * Partial indexes are not portable, so it is an ordinary composite
             * — the null rows cost a little space and save a scan.
             */
            $table->index(['escalated_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['escalated_at', 'status']);
            $table->dropColumn(['escalated_at', 'escalated_by', 'escalation_reason']);
        });
    }
};
