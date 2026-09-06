<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain;

use App\Modules\Platform\Exceptions\ProblemException;
use Carbon\CarbonImmutable;

/**
 * The one filter this whole surface has.
 *
 * A date range and nothing else. No department, no channel, no priority, no
 * agent, no category, no branch — and no report builder, no custom query, no
 * ad-hoc modelling and no saved report. Every one of those is a way of asking
 * a question this product cannot promise a trustworthy answer to, and a figure
 * nobody trusts is worse than no figure.
 *
 * Inclusive at both ends, because that is what somebody picking "1 to 31
 * March" means. Expressed by taking the whole of the last day rather than by
 * a `<` on the next one, so the boundary is visible where it is decided.
 */
final readonly class ReportPeriod
{
    /** A year. Long enough for "last year", short enough to stay interactive. */
    private const MAXIMUM_DAYS = 366;

    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function between(string $from, string $to): self
    {
        try {
            $start = CarbonImmutable::parse($from)->startOfDay();
            $end = CarbonImmutable::parse($to)->endOfDay();
        } catch (\Throwable) {
            throw ProblemException::make(
                'reporting.bad_period',
                'That is not a date range',
                422,
                'Give a start and an end date, in that order.',
            );
        }

        if ($end < $start) {
            /*
             * Refused rather than silently swapped. Somebody who typed the
             * dates the wrong way round has made a mistake, and quietly
             * answering a different question than they asked is how a figure
             * ends up in a slide with nobody able to reproduce it.
             */
            throw ProblemException::make(
                'reporting.period_backwards',
                'The end is before the start',
                422,
                'The end date must be on or after the start date.',
                ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            );
        }

        if ($start->diffInDays($end) > self::MAXIMUM_DAYS) {
            throw ProblemException::make(
                'reporting.period_too_long',
                'That range is too long',
                422,
                'Reports cover up to a year at a time. Narrow the range.',
                ['maximum_days' => self::MAXIMUM_DAYS],
            );
        }

        return new self($start, $end);
    }

    /**
     * The range as the ticket list expects it, so a card's click-through lands
     * on exactly the tickets it counted.
     *
     * @return array{created_from: string, created_to: string}
     */
    public function asTicketListFilters(): array
    {
        return [
            'created_from' => $this->from->toDateString(),
            'created_to' => $this->to->toDateString(),
        ];
    }
}
