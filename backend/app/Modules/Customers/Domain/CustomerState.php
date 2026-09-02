<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

/**
 * Active or inactive. There is no deleted.
 *
 * A customer record is the anchor for every ticket, note and interaction
 * attached to it. Removing the row would orphan all of that history, so the
 * product deactivates instead: the record stops appearing in search and stays
 * reachable by a direct link, with everything it ever accumulated intact.
 */
enum CustomerState: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
