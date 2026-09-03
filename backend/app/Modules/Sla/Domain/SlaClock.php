<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Carbon\CarbonImmutable;

/**
 * Reads both timers on a ticket.
 *
 * Two clocks with deliberately different rules:
 *
 *   RESPONSE runs from creation to the first agent reply, and NEVER restarts.
 *   The promise is "somebody will get back to you", and it is kept or broken
 *   once. Restarting it on a later message would let a desk that answered in
 *   four minutes and then went silent for a week look permanently on track.
 *
 *   RESOLUTION runs from creation to `resolved_at`, and PAUSES while the ticket
 *   is waiting on the customer. Counting the days a customer took to send a
 *   screenshot against the desk's target would make the number measure the
 *   customer rather than the service.
 *
 * Both go through WorkingHoursCalculator, which is the only place business
 * hours are understood.
 */
final class SlaClock
{
    public const RESPONSE = 'response';

    public const RESOLUTION = 'resolution';

    public function __construct(
        private readonly WorkingHoursCalculator $hours,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @return array{response: TimerReading, resolution: TimerReading}
     */
    public function read(TicketTimeline $ticket, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');

        return [
            self::RESPONSE => $this->response($ticket, $now),
            self::RESOLUTION => $this->resolution($ticket, $now),
        ];
    }

    private function response(TicketTimeline $ticket, CarbonImmutable $now): TimerReading
    {
        $target = $this->targetMinutes(self::RESPONSE, $ticket->priority);

        if ($ticket->firstAgentReplyAt !== null) {
            /*
             * Stopped, and stopped for good. The state is `met` or `breached`
             * on the evidence of when the reply actually went — not on where
             * the clock would be now.
             */
            $elapsed = $this->hours->workingMinutesBetween($ticket->createdAt, $ticket->firstAgentReplyAt);

            return new TimerReading(
                state: $elapsed > $target ? SlaState::Breached : SlaState::Met,
                elapsedMinutes: $elapsed,
                targetMinutes: $target,
                remainingMinutes: $target - $elapsed,
                dueAt: null,
            );
        }

        /*
         * Response does NOT pause while waiting on the customer. Nobody has
         * replied yet, so there is nothing for the customer to be waiting on —
         * a ticket in `pending` with no agent reply is a ticket the desk has
         * not touched, whatever its status says.
         */
        $elapsed = $this->hours->workingMinutesBetween($ticket->createdAt, $now);

        return $this->live($elapsed, $target, $this->hours->addWorkingMinutes($ticket->createdAt, $target), $now);
    }

    private function resolution(TicketTimeline $ticket, CarbonImmutable $now): TimerReading
    {
        $target = $this->targetMinutes(self::RESOLUTION, $ticket->priority);
        $stopAt = $ticket->resolvedAt ?? $now;

        $elapsed = $this->hours->workingMinutesBetween($ticket->createdAt, $stopAt)
            - $this->pausedMinutes($ticket, $stopAt);

        // Clamped: a pause recorded slightly outside the measured interval —
        // clock skew, a status change at the same second — must not produce a
        // negative elapsed time and a ticket that looks better than new.
        $elapsed = max(0, $elapsed);

        if ($ticket->isResolved()) {
            return new TimerReading(
                state: $elapsed > $target ? SlaState::Breached : SlaState::Met,
                elapsedMinutes: $elapsed,
                targetMinutes: $target,
                remainingMinutes: $target - $elapsed,
                dueAt: null,
            );
        }

        if ($ticket->isPausedNow()) {
            /*
             * Paused, and said so. Showing a waiting-on-customer ticket as
             * on_track would put it in an agent's "act on this" list for work
             * that is not theirs to do.
             */
            return new TimerReading(
                state: SlaState::Paused,
                elapsedMinutes: $elapsed,
                targetMinutes: $target,
                remainingMinutes: $target - $elapsed,
                dueAt: null,
            );
        }

        return $this->live($elapsed, $target, $this->dueAt($ticket, $target, $elapsed, $now), $now);
    }

    /**
     * The state of a clock that is still running.
     */
    private function live(int $elapsed, int $target, CarbonImmutable $dueAt, CarbonImmutable $now): TimerReading
    {
        $threshold = (int) $this->settings->get('sla.at_risk_threshold_percent');

        $state = match (true) {
            $elapsed > $target => SlaState::Breached,
            // At risk BEFORE breaching, which is the entire point: a warning
            // that arrives with the breach is not a warning.
            $target > 0 && ($elapsed * 100) >= ($target * $threshold) => SlaState::AtRisk,
            default => SlaState::OnTrack,
        };

        return new TimerReading(
            state: $state,
            elapsedMinutes: $elapsed,
            targetMinutes: $target,
            remainingMinutes: $target - $elapsed,
            dueAt: $dueAt->toIso8601ZuluString(),
        );
    }

    /**
     * When a running resolution timer runs out.
     *
     * Measured forward from NOW by what is left, not from creation by the whole
     * target: the pauses already taken have pushed the deadline out, and
     * recomputing from the start would ignore them.
     */
    private function dueAt(TicketTimeline $ticket, int $target, int $elapsed, CarbonImmutable $now): CarbonImmutable
    {
        return $this->hours->addWorkingMinutes($now, max(0, $target - $elapsed));
    }

    /** Working minutes the ticket spent waiting on the customer. */
    private function pausedMinutes(TicketTimeline $ticket, CarbonImmutable $stopAt): int
    {
        $paused = 0;

        foreach ($ticket->pausedIntervals as $interval) {
            $from = $interval['from'];
            $to = $interval['to'] ?? $stopAt;

            if ($to > $stopAt) {
                $to = $stopAt;
            }

            $paused += $this->hours->workingMinutesBetween($from, $to);
        }

        return $paused;
    }

    private function targetMinutes(string $timer, string $priority): int
    {
        $seconds = (int) $this->settings->get("sla.{$timer}_target_seconds.{$priority}");

        return (int) max(1, floor($seconds / 60));
    }
}
