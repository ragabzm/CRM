<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Assignment;

/**
 * Where a mapping sends a ticket.
 *
 * An agent, or a department. There is deliberately no third: "the least busy
 * agent", "round robin" and "whoever is on shift" each need an availability
 * model that was removed with chat presence, and each is named in the story as
 * deferred rather than missing.
 */
enum MappingTarget: string
{
    case Agent = 'agent';
    case Department = 'department';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
