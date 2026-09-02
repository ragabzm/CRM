<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three moments the lifecycle turns on.
 *
 * All nullable and additive: rows created before this migration simply have no
 * resolution yet, which is the truth rather than a default that would make the
 * sweep close them immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            // When the work was declared done. The auto-close clock starts here.
            $table->timestamp('resolved_at')->nullable()->after('status');

            // When it was closed. The reopen window is measured from here, so a
            // ticket closed by the sweep and one closed by hand behave alike.
            $table->timestamp('closed_at')->nullable()->after('resolved_at');

            /*
             * The last time the CUSTOMER did something. Separate from
             * `updated_at`, which any agent edit would bump — closing a ticket
             * the customer replied to yesterday because a colleague retagged it
             * this morning is exactly the bug this column prevents.
             */
            $table->timestamp('last_customer_activity_at')->nullable()->after('closed_at');

            // The sweep's query, in the order it filters.
            $table->index(['status', 'resolved_at', 'last_customer_activity_at'], 'tickets_auto_close_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_auto_close_index');
            $table->dropColumn(['resolved_at', 'closed_at', 'last_customer_activity_at']);
        });
    }
};
