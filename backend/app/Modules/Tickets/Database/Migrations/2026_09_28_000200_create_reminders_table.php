<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Come back to this at nine tomorrow."
 *
 * One row, one owner, one moment. There is no snooze column and no repeat
 * rule: a reminder that can be pushed is a reminder nobody acts on, and a
 * repeating one is a scheduler.
 *
 * `fired_at` is what makes the sweep idempotent. It is written in the SAME
 * transaction as the dispatch, so a second run — a slow minute, two workers,
 * a retried job — selects nothing rather than sending the same reminder twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Exactly one of these, enforced below.
            $table->string('ticket_id', 26)->nullable();
            $table->ulid('task_id')->nullable();

            $table->timestamp('remind_at');
            $table->timestamp('fired_at')->nullable();

            $table->timestamps();

            /*
             * The sweep's own index: unfired reminders whose moment has come.
             * Without it the minutely query is a full scan of every reminder
             * ever set, and the table only grows.
             */
            $table->index(['fired_at', 'remind_at']);
            $table->index(['user_id', 'fired_at']);
        });

        /*
         * A reminder points at exactly one thing.
         *
         * Not "at least one": a row naming both a ticket and a task would
         * render twice on the Home tab and notify about something the owner
         * cannot identify. Not "at most one" either — a reminder about nothing
         * has nothing to open.
         *
         * In the database rather than only in the command, because the command
         * is one door and a migration, a seeder or a console script is another.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE reminders ADD CONSTRAINT reminders_target_check CHECK (
                (ticket_id IS NULL) <> (task_id IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
