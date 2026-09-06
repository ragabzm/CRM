<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain;

use Illuminate\Support\Facades\DB;

/**
 * Are customers happy.
 *
 * One figure: the positive rate — thumbs up as a proportion of RATINGS GIVEN.
 * The denominator is the number of people who answered, never the number of
 * tickets closed. Those are two different numbers and only one of them answers
 * the question: a desk that closed a thousand tickets and got four ratings,
 * three of them good, is at seventy-five per cent with a response volume of
 * four — and reporting it as 0.3% would be reporting how few people replied,
 * dressed as how badly the desk did.
 *
 * There is no average out of five, because there is no five. Satisfaction is a
 * boolean by design (Story 11.1), so nothing here converts or normalises
 * anything, and comparing this quarter to last is a subtraction rather than an
 * argument about scales.
 */
final class SatisfactionReport
{
    /**
     * @return array<string, mixed>
     */
    public function forPeriod(ReportPeriod $period): array
    {
        /*
         * By `satisfaction_at`, not by when the ticket was created.
         *
         * A rating given in March is March's news even if the ticket was
         * raised in February — and the alternative would leave a period's
         * figure changing for weeks after it ended, which is the property that
         * makes a report unciteable.
         */
        $row = DB::table('tickets')
            ->whereNotNull('satisfaction')
            ->whereBetween('satisfaction_at', [$period->from, $period->to])
            ->selectRaw('count(*) as answered')
            ->selectRaw('count(case when satisfaction = true then 1 end) as positive')
            ->first();

        $answered = (int) ($row?->answered ?? 0);
        $positive = (int) ($row?->positive ?? 0);

        return [
            // The response VOLUME, shown beside the rate rather than behind
            // it: a rate over four answers and a rate over four hundred are
            // not the same claim.
            'answered' => $answered,
            'positive' => $positive,
            'negative' => $answered - $positive,

            /*
             * Null when nobody answered. Zero per cent would say every
             * customer who replied was unhappy, which is a different and much
             * worse thing than nobody replying.
             */
            'positive_rate' => $answered === 0 ? null : round($positive / $answered, 4),

            /*
             * Three click-throughs, one per figure, because a figure with no
             * click-through does not ship. `rated` is the denominator — the
             * people who answered — which is what the rate is a proportion of.
             */
            'filters' => [...$period->asTicketListFilters(), 'satisfaction' => 'rated'],
            'positive_filters' => [...$period->asTicketListFilters(), 'satisfaction' => 'positive'],
            'negative_filters' => [...$period->asTicketListFilters(), 'satisfaction' => 'negative'],
        ];
    }
}
