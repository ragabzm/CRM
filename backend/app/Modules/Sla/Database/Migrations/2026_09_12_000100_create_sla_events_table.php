<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A breach that happened, recorded once.
 *
 * The STATE of a timer is never stored — it is computed on read, because a
 * stored state is wrong the second after it is written and needs a job to keep
 * it fresh, which is a job that can fall behind and lie.
 *
 * A breach is different. It is an event, not a state: it happened at a moment,
 * and it stays true afterwards even when the ticket is resolved and the timer
 * stops. Reporting on "how many targets did we miss last quarter" needs the
 * event; recomputing it later would give a different answer every time the
 * working-hours schedule or a target changed.
 *
 * `(ticket_id, target)` is UNIQUE. The sweep runs every minute and will see the
 * same breach sixty times an hour; the index is what makes the second through
 * sixtieth writes lose rather than accumulate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('ticket_id')->constrained('tickets')->cascadeOnDelete();

            /** `response` | `resolution`. */
            $table->string('target', 16);

            /*
             * What the ticket was worth at the moment it breached. Recorded
             * rather than looked up later: priorities get changed, and a
             * breach of a P1 target does not retroactively become a breach of a
             * P4 one because somebody downgraded the ticket afterwards.
             */
            $table->string('priority', 16);
            $table->unsignedInteger('target_minutes');
            $table->unsignedInteger('elapsed_minutes');

            $table->timestamp('breached_at');
            $table->timestamps();

            // The whole idempotency mechanism.
            $table->unique(['ticket_id', 'target']);

            // "What breached this week", which is how the record is read.
            $table->index(['breached_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_events');
    }
};
