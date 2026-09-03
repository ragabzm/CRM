<?php

declare(strict_types=1);

namespace App\Modules\Sla\Console\Commands;

use App\Modules\Tickets\Notifications\SlaWarning;
use App\Modules\Tickets\Notifications\TicketNotifier;
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

    public function __construct(private readonly TicketNotifier $notifier)
    {
        parent::__construct();
    }

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
                        /*
                         * At-risk warns; breached records AND warns.
                         *
                         * The at-risk notification is the one that matters: a
                         * warning that arrives with the breach is not a
                         * warning, it is a report. It is deliberately NOT
                         * persisted — an at-risk ticket that gets answered was
                         * never a problem, and a row saying otherwise would
                         * make the reporting look worse than the service was.
                         */
                        if ($reading->state === SlaState::AtRisk) {
                            $this->warn_($timeline, $target, $reading, SlaWarning::AT_RISK);

                            continue;
                        }

                        if ($reading->state !== SlaState::Breached) {
                            continue;
                        }

                        /*
                         * Only the FIRST sweep that sees a breach notifies.
                         * The insert losing on the unique index is what makes
                         * that true — without it the sweep would email the
                         * assignee once a minute for as long as the ticket
                         * stayed late.
                         */
                        if ($this->record($timeline->ticketId, $target, $timeline->priority, $reading, $now)) {
                            $recorded++;
                            $this->warn_($timeline, $target, $reading, SlaWarning::BREACHED);
                        }
                    }
                }
            });

        $this->info(sprintf('Examined %d live tickets; recorded %d new breaches.', $seen, $recorded));

        return self::SUCCESS;
    }

    /**
     * Tells whoever needs to know.
     *
     * On a BREACH the department's supervisors hear about it as well as the
     * assignee: an at-risk ticket is the assignee's to save, while a missed
     * target is the team's problem and somebody has to be able to reassign it.
     */
    private function warn_(
        \App\Modules\Sla\Domain\TicketTimeline $timeline,
        string $target,
        \App\Modules\Sla\Domain\TimerReading $reading,
        string $state,
    ): void {
        $facts = $this->notifier->ticketFacts($timeline->ticketId);

        if ($facts === null) {
            return;
        }

        $notification = new SlaWarning(
            $facts['id'],
            $facts['reference'],
            $facts['subject'],
            $state,
            $target,
            abs($reading->remainingMinutes),
        );

        // No actor: nobody did this, a deadline passed.
        $this->notifier->notifyAssignee($facts['assignee_id'], null, $notification);

        if ($state === SlaWarning::BREACHED) {
            $this->notifier->notifyDepartmentSupervisors(
                $facts['department_id'],
                $facts['assignee_id'],
                $notification,
            );
        }
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
