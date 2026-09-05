<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this ticket first reached a finished state.
 *
 * Not the same as "when it was resolved". A ticket can be resolved, reopened
 * and resolved again; `status` says where it is now and `ticket_events` says
 * how it got there, but neither answers "has this conversation ever been
 * finished before?" without a scan.
 *
 * That question has exactly one caller today and it is the reason this column
 * exists: the customer is invited to say how it went ONCE. Without a recorded
 * first time, a ticket that is resolved, reopened and resolved again would
 * email the same person the same question twice — which is the "chasing" the
 * story refuses in as many words.
 *
 * Written once and never cleared. Reopening does not un-finish the past.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('first_finished_at')->nullable()->after('satisfaction_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('first_finished_at');
        });
    }
};
