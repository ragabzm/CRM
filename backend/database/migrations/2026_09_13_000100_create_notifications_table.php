<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's own notifications table, plus the one index this product needs.
 *
 * Lives in the shared migrations directory rather than a module's: the table is
 * framework-owned and every module that notifies writes to it. Putting it in
 * one module would make the others depend on that module's schema for a table
 * none of them owns.
 *
 * The extra index is the whole reason this is not `notifications:table`
 * verbatim. The bell asks "how many unread for me?" on every page load, and
 * without a partial index that becomes a scan of every notification ever sent
 * to anybody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        /*
         * "Unread, for this person, newest first" — the query the bell makes on
         * every page load. `read_at` leads nothing: it is included so the index
         * answers the count without touching the table at all.
         */
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'notifications_recipient_unread_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
