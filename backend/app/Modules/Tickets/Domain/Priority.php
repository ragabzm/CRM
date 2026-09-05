<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

/**
 * The four ticket priorities. Fixed.
 *
 * An enum rather than a table, deliberately. Priority is not data an
 * administrator tunes — it is a vocabulary the whole product reasons about:
 * SLA targets are keyed on it, the queue sorts by it, reports group by it. A
 * fifth priority invented at runtime would silently have no SLA target and no
 * sort position, so the console shows these as read-only and SAYS they are
 * fixed rather than omitting the section and leaving an administrator hunting.
 */
enum Priority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * The next step up, or this one when there is nowhere further.
     *
     * Urgent returns Urgent rather than throwing. A breach sweep raising the
     * priority of a ticket that is already Urgent has nothing to do and no
     * problem to report — refusing would turn a routine no-op into an error in
     * a log nobody can act on, once a minute, for as long as the ticket stays
     * late.
     */
    public function raisedOneStep(): self
    {
        return match ($this) {
            self::Low => self::Normal,
            self::Normal => self::High,
            self::High, self::Urgent => self::Urgent,
        };
    }

    /**
     * In severity order, lowest first. The order is part of the contract —
     * the console renders it and SLA matrices are keyed by it.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
