<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * THE business-hours arithmetic. There is exactly one of these.
 *
 * "Four hours to respond" does not mean four hours. It means four hours the
 * desk was open — a ticket raised at 16:30 on Thursday is not late at 20:30
 * that evening, and a customer would be astonished to hear otherwise. Every
 * timer, every indicator and every breach decision has to agree about that, and
 * the only way to guarantee agreement is for them all to call the same code.
 *
 * `SingleWorkingHoursSourceTest` fails if anything else does this arithmetic.
 *
 * UTC in, UTC out. The schedule is written in a local timezone — that is what
 * "09:00" means — but no caller ever sees a local time from here. Converting at
 * the boundary and back is what stops a daylight-saving change quietly moving
 * every deadline.
 */
final class WorkingHoursCalculator
{
    /** Weekday keys, in the order PHP's `N` format does not use. */
    private const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Working minutes between two instants.
     *
     * Returns 0 rather than a negative when `$to` precedes `$from`: a caller
     * asking about a backwards interval has a bug, and a negative elapsed time
     * would silently make a breached ticket look on track.
     */
    public function workingMinutesBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        if ($to <= $from) {
            return 0;
        }

        $zone = $this->timezone();
        $localFrom = $from->setTimezone($zone);
        $localTo = $to->setTimezone($zone);

        $minutes = 0;
        $day = $localFrom->startOfDay();

        /*
         * Day by day rather than by a closed-form formula. A formula has to
         * special-case holidays, closed weekdays, partial first and last days,
         * and any future change to the schedule shape — and each special case
         * is somewhere to be subtly wrong about a boundary nobody tests.
         *
         * A year of daily iteration is 365 cheap steps; SLA targets are hours,
         * so in practice this runs a handful of times.
         */
        while ($day <= $localTo) {
            $window = $this->windowFor($day);

            if ($window !== null) {
                [$open, $close] = $window;

                // The part of this day's window that falls inside the interval.
                $start = $localFrom->greaterThan($open) ? $localFrom : $open;
                $end = $localTo->lessThan($close) ? $localTo : $close;

                if ($end > $start) {
                    $minutes += (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
                }
            }

            $day = $day->addDay()->startOfDay();
        }

        return $minutes;
    }

    /**
     * The instant that is N working minutes after a given one.
     *
     * What the admin editor previews ("4 working hours from now is Tuesday
     * 11:00") and what a breach deadline is. Same schedule, same holidays, so a
     * preview cannot disagree with the timer it is previewing.
     */
    public function addWorkingMinutes(CarbonImmutable $from, int $minutes): CarbonImmutable
    {
        if ($minutes <= 0) {
            return $from;
        }

        $zone = $this->timezone();
        $cursor = $from->setTimezone($zone);
        $remaining = $minutes;

        /*
         * Bounded. A schedule with every day closed — which the validator
         * permits, because "we are shut this week" is a legitimate thing to
         * configure — would otherwise spin forever. Two years is far past any
         * real target and short enough to fail visibly rather than hang.
         */
        $limit = $cursor->addYears(2);

        while ($remaining > 0 && $cursor < $limit) {
            $window = $this->windowFor($cursor->startOfDay());

            if ($window === null) {
                $cursor = $cursor->addDay()->startOfDay();

                continue;
            }

            [$open, $close] = $window;

            // Before opening: the clock has not started today.
            if ($cursor < $open) {
                $cursor = $open;
            }

            if ($cursor >= $close) {
                $cursor = $cursor->addDay()->startOfDay();

                continue;
            }

            $availableToday = (int) floor(($close->getTimestamp() - $cursor->getTimestamp()) / 60);

            if ($availableToday >= $remaining) {
                return $cursor->addMinutes($remaining)->setTimezone('UTC');
            }

            $remaining -= $availableToday;
            $cursor = $cursor->addDay()->startOfDay();
        }

        return $cursor->setTimezone('UTC');
    }

    /**
     * Whether the desk is open at a given instant.
     *
     * Used by the state resolver to say "paused because we are closed" rather
     * than counting a quiet Friday against the target.
     */
    public function isOpenAt(CarbonImmutable $at): bool
    {
        $local = $at->setTimezone($this->timezone());
        $window = $this->windowFor($local->startOfDay());

        return $window !== null && $local >= $window[0] && $local < $window[1];
    }

    /**
     * Today's opening and closing instants, or null when closed.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function windowFor(CarbonImmutable $localDay): ?array
    {
        if ($this->isHoliday($localDay)) {
            return null;
        }

        $hours = $this->schedule()[self::DAYS[(int) $localDay->format('w')]] ?? null;

        if (! is_array($hours) || count($hours) !== 2) {
            // null is a CLOSED day, and is a decision somebody made rather than
            // a gap in the configuration.
            return null;
        }

        [$openAt, $closeAt] = $hours;

        return [
            $localDay->setTimeFromTimeString((string) $openAt),
            $localDay->setTimeFromTimeString((string) $closeAt),
        ];
    }

    private function isHoliday(CarbonImmutable $localDay): bool
    {
        $holidays = $this->settings->get('sla.holidays');

        return is_array($holidays) && in_array($localDay->format('Y-m-d'), $holidays, true);
    }

    /** @return array<string, mixed> */
    private function schedule(): array
    {
        $hours = $this->settings->get('sla.working_hours');

        return is_array($hours) ? $hours : [];
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) $this->settings->get('sla.timezone'));
    }
}
