<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The thing an agent must not forget.
 *
 * Deliberately the smallest table that makes the scenario work, and the
 * columns that are ABSENT are the design: no description, no assignee, no
 * parent id, no recurrence rule, no checklist, no dependency. Each of those is
 * one step towards a project tracker living inside a helpdesk, and each would
 * arrive with a screen, a permission and an argument about whose task it is.
 *
 * `user_id` is the owner and the creator at once — a task cannot be handed to
 * anybody, which is what keeps it PERSONAL work rather than a second, quieter
 * assignment queue running beside the real one.
 *
 * `ticket_id` is nullable, and that null is the whole "standalone task"
 * feature. A separate table for unattached tasks would double every query on
 * this screen to express one missing foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('title', 200);

            /*
             * Nullable, and cleared rather than cascading when the ticket goes.
             * A task about a deleted ticket is still a note to self and still
             * has to be closable; deleting it with the ticket would remove
             * work the agent had not done yet.
             */
            $table->string('ticket_id', 26)->nullable();

            $table->timestamp('due_at')->nullable();

            /*
             * Completion is a TIMESTAMP, not a boolean, because "when did you
             * finish it" is the question asked immediately afterwards and a
             * boolean cannot answer it. Null means open.
             */
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // The Home tab: my open tasks, soonest first. One index serves the
            // list and the count beside it.
            $table->index(['user_id', 'completed_at', 'due_at']);
            $table->index(['ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
