<?php

declare(strict_types=1);

namespace App\Modules\Sla\Console\Commands;

use App\Modules\Sla\Domain\SlaClock;
use App\Modules\Sla\Domain\SlaState;
use App\Modules\Sla\Domain\TicketTimelineLoader;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Notices breaches, and writes each one down once.
 *
 * A breach has to be caught by something that runs on its own. Waiting for
 * somebody to open the ticket would mean a target missed at 02:00 on a quiet
 * ticket nobody looks at is never recorded at all — and those are exactly the
 * ones worth knowing about.
 *
 * The sweep is idempotent by construction: it re-derives every live ticket's
 * state and tries to insert. A unique index on `(ticket_id, target)` makes the
 * repeats lose. That is deliberately not a "have I seen this?" check — two
 * sweeps overlapping after a slow minute would both see "not recorded" and both
 * write.
 */
final class SweepSlaBreachesCommand extends Command
{
    protected $signature = 'sla:sweep {--dry-run : Report what would be recorded and write nothing}';

    protected $description = 'Record SLA breaches for tickets whose targets have passed.';

    /** Enough to keep one pass short; the sweep runs again in a minute. */
    private const BATCH = 500;

    public function handle(TicketTimelineLoader $loader, SlaClock $clock): int
    {
        $now = CarbonImmutable::now('UTC');
        $recorded = 0;
        $seen = 0;

        /*
         * Only tickets that can still breach. A resolved ticket's outcome was
         * decided at the moment it was resolved, and re-examining every closed
         * ticket every minute forever would make this sweep grow without bound.
         */
        DB::table('tickets')
            ->whereIn('status', ['open', 'pending'])
            ->orderBy('id')
            ->select('id')
            ->chunk(self::BATCH, function ($chunk) use ($loader, $clock, $now, &$recorded, &$seen): void {
                $ids = $chunk->pluck('id')->map(static fn ($id): string => (string) $id)->all();
                $timelines = $loader->forTickets($ids);

                foreach ($timelines as $timeline) {
                    $seen++;

                    foreach ($clock->read($timeline, $now) as $target => $reading) {
                        if ($reading->state !== SlaState::Breached) {
                            continue;
                        }

                        if ($this->record($timeline->ticketId, $target, $timeline->priority, $reading, $now)) {
                            $recorded++;
                        }
                    }
                }
            });

        $this->info(sprintf('Examined %d live tickets; recorded %d new breaches.', $seen, $recorded));

        return self::SUCCESS;
    }

    private function record(
        string $ticketId,
        string $target,
        string $priority,
        \App\Modules\Sla\Domain\TimerReading $reading,
        CarbonImmutable $now,
    ): bool {
        if ($this->option('dry-run')) {
            $this->line(sprintf('  would record %s %s (%d/%d min)', $ticketId, $target, $reading->elapsedMinutes, $reading->targetMinutes));

            return false;
        }

        try {
            DB::table('sla_events')->insert([
                'id' => (string) Str::ulid(),
                'ticket_id' => $ticketId,
                'target' => $target,
                'priority' => $priority,
                'target_minutes' => $reading->targetMinutes,
                'elapsed_minutes' => $reading->elapsedMinutes,
                'breached_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        } catch (QueryException) {
            // Already recorded. This is the expected path on all but the first
            // sweep after a breach.
            return false;
        }
    }
}
