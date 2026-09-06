<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "What breached in this period, and against which target."
 *
 * The story names this index as `(occurred_at, target_type)`. This table's
 * columns are `breached_at` and `target` — the names it shipped with in Story
 * 5.3 — and the index is on those. Writing it against the names in the brief
 * would have produced a migration that fails on the first run; recording the
 * difference here is cheaper than somebody rediscovering it.
 *
 * There is already an index on `breached_at` alone. This one adds the target,
 * because every compliance figure is asked per target: a desk hits its
 * response times and misses its resolution times, and one number covering both
 * would hide exactly the thing worth knowing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_events', function (Blueprint $table): void {
            $table->index(['breached_at', 'target'], 'sla_events_breached_at_target_index');
        });
    }

    public function down(): void
    {
        Schema::table('sla_events', function (Blueprint $table): void {
            $table->dropIndex('sla_events_breached_at_target_index');
        });
    }
};
