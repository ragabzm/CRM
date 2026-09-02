<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ticket.
 *
 * `version` is the whole point of the optimistic-concurrency design: two agents
 * editing the same ticket both submit the version they read, and the second one
 * is refused rather than silently overwriting the first. A last-write-wins
 * column would lose a status change nobody knew had happened.
 *
 * Deliberately no `ticket_categories` table here — Story 2.3 already shipped one
 * with a bilingual name and an admin console. Its key is a bigint, so this FK is
 * a foreignId rather than the ulid the plan assumed; one categories table with
 * one admin surface beats two that drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Quoted over the phone and written on other people's notes, so it
            // is short and sequential rather than the 26-character ULID.
            $table->string('reference', 16)->unique();

            $table->string('subject', 200);
            $table->text('description');

            $table->foreignUlid('customer_id')->constrained('customers')->restrictOnDelete();

            // Where the ticket came from. Persisted as a string so a new
            // channel is a code change, not a schema change.
            $table->string('channel', 24);

            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('open');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            /*
             * The creator is recorded as a type and an id with NO foreign key.
             * A creator may be a staff user, a portal account or the system
             * itself — three different tables and, for the system, none at all.
             */
            $table->string('creator_type', 16);
            $table->string('creator_id', 26)->nullable();

            // Nulled rather than cascaded: a deactivated agent's tickets return
            // to the unassigned pool instead of vanishing.
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // The two queries the workspace will ask: "my department's open
            // work" and "what am I holding".
            $table->index(['status', 'department_id']);
            $table->index(['assignee_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });

        $this->addConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }

    private function addConstraints(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('open','pending','resolved','closed','reopened'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_priority_check CHECK (priority IN ('low','normal','high','urgent'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_channel_check CHECK (channel IN ('agent','portal','email','system'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_creator_type_check CHECK (creator_type IN ('staff','portal','system'))");
    }
};
