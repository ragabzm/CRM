<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain;

use Carbon\CarbonImmutable;

/**
 * Everything about one ticket that the SLA clock depends on.
 *
 * Assembled once and passed in, rather than each timer querying for itself.
 * Reading a ticket list means reading this for twenty-five tickets, and a
 * per-timer query would be fifty round trips for one screen.
 *
 * Flat and primitive: the Sla module (T4) reads Tickets' tables through the
 * query builder and never learns its models.
 */
final readonly class TicketTimeline
{
    /**
     * @param  list<array{from: CarbonImmutable, to: CarbonImmutable|null}>  $pausedIntervals
     *         Periods the ticket spent waiting on the customer. `to` is null
     *         for a pause that has not ended.
     */
    public function __construct(
        public string $ticketId,
        public string $priority,
        public string $status,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $firstAgentReplyAt,
        public ?CarbonImmutable $resolvedAt,
        public array $pausedIntervals,
    ) {}

    public function isResolved(): bool
    {
        return $this->resolvedAt !== null;
    }

    public function isPausedNow(): bool
    {
        foreach ($this->pausedIntervals as $interval) {
            if ($interval['to'] === null) {
                return true;
            }
        }

        return false;
    }
}
