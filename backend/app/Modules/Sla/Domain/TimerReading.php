<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain;

/**
 * One timer, read at a moment.
 *
 * Computed, never stored. A stored state is wrong the second after it is
 * written — the clock keeps moving — and a system that stores it needs a job to
 * keep every row fresh, which is a job that can fall behind and lie.
 *
 * Only BREACHES are persisted, and separately, because a breach is an event
 * that happened rather than a state that is true.
 */
final readonly class TimerReading
{
    public function __construct(
        public SlaState $state,
        /** Working minutes counted so far against this target. */
        public int $elapsedMinutes,
        public int $targetMinutes,
        /**
         * Working minutes left. Negative once breached — by how much is the
         * thing a supervisor actually asks about.
         */
        public int $remainingMinutes,
        /** When the target runs out, in UTC. Null once the timer has stopped. */
        public ?string $dueAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'elapsed_minutes' => $this->elapsedMinutes,
            'target_minutes' => $this->targetMinutes,
            'remaining_minutes' => $this->remainingMinutes,
            'due_at' => $this->dueAt,
        ];
    }
}
