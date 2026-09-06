<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain;

use App\Modules\Sla\Domain\SlaReaderService;
use Illuminate\Support\Facades\DB;

/**
 * Did we hit our targets, and how long did things actually take.
 *
 * TWO SOURCES, and keeping them apart is the whole correctness argument.
 *
 * COMPLIANCE comes from `sla_events` — rows written at the moment a target was
 * missed, carrying the target that was in force then. It is never recalculated
 * from today's targets. An administrator who tightens the response target this
 * morning has not made last March worse, and a report that said otherwise
 * would be a report nobody could take to a meeting.
 *
 * AVERAGES come from the Sla module's own arithmetic, which this class calls
 * and does not reimplement. A naive wall-clock difference would count nights
 * and weekends and disagree with the ticket's own SLA badge — and the customer
 * would find the disagreement first.
 *
 * No code here evaluates a target. There is not a target key in this file.
 */
final class SlaPerformance
{
    /** The two clocks. There is no third. */
    private const TARGETS = ['response', 'resolution'];

    public function __construct(private readonly SlaReaderService $sla) {}

    /**
     * @return array<string, mixed>
     */
    public function forPeriod(ReportPeriod $period): array
    {
        $ticketIds = $this->ticketsRaisedIn($period);
        $breaches = $this->breachesAmong($ticketIds);
        $elapsed = $this->sla->elapsedMinutesAmong($ticketIds);

        $total = count($ticketIds);

        $performance = [];

        foreach (self::TARGETS as $target) {
            $missed = $breaches[$target] ?? 0;

            $performance[$target] = [
                'breaches' => $missed,
                /*
                 * Null, not 100%, when there is nothing to measure. An empty
                 * period that reported perfect compliance would be a desk
                 * congratulating itself on a month it did not work.
                 */
                'compliance_rate' => $total === 0 ? null : round(($total - $missed) / $total, 4),
                'average_minutes' => $this->average($elapsed[$target] ?? []),
                'measured' => count($elapsed[$target] ?? []),
                'filters' => [...$period->asTicketListFilters(), 'sla_state' => 'breached'],
            ];
        }

        return [
            'tickets' => $total,
            'breaches' => array_sum($breaches),
            'response' => $performance['response'],
            'resolution' => $performance['resolution'],
        ];
    }

    /**
     * The tickets the period is about.
     *
     * Raised in the range, which is the same set every other figure on this
     * surface counts. Compliance measured over "tickets that breached in the
     * range" instead would have a denominator that moves with the numerator
     * and could never fall below a hundred per cent.
     *
     * @return list<string>
     */
    private function ticketsRaisedIn(ReportPeriod $period): array
    {
        return DB::table('tickets')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * Recorded breaches, per target.
     *
     * Read from the row that was written when it happened. Nothing here asks
     * what the target is now.
     *
     * @param  list<string>  $ticketIds
     * @return array<string, int>
     */
    private function breachesAmong(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        return DB::table('sla_events')
            ->whereIn('ticket_id', $ticketIds)
            ->groupBy('target')
            ->pluck(DB::raw('count(*)'), 'target')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<int>  $minutes
     */
    private function average(array $minutes): ?int
    {
        if ($minutes === []) {
            // Nothing was measured. Zero would read as "instant".
            return null;
        }

        return (int) round(array_sum($minutes) / count($minutes));
    }
}
