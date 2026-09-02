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
