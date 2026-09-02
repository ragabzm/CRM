<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Enum;

/**
 * How a ticket reached us.
 *
 * Recorded because the answer changes what a reply should look like and where
 * it goes — and because "how do most of our tickets arrive?" is a question
 * nobody can answer retrospectively unless it was captured at creation.
 */
enum TicketChannel: string
{
    case Agent = 'agent';
    case Portal = 'portal';
    case Email = 'email';
    case System = 'system';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
