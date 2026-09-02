<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Reference;

/**
 * The shape of a ticket reference, in one place.
 *
 * Six zero-padded digits so references sort lexicographically for the first
 * million tickets — which is what lets the SQLite allocator take a plain MAX()
 * and what makes a sorted list of references read in creation order.
 */
final class TicketReference
{
    public const PREFIX = 'TKT-';

    public const DIGITS = 6;

    public const PATTERN = '/^TKT-\d{6,}$/';

    public static function format(int $number): string
    {
        return self::PREFIX.str_pad((string) $number, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function isValid(string $reference): bool
    {
        return preg_match(self::PATTERN, $reference) === 1;
    }
}
