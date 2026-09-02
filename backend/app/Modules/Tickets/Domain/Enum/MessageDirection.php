<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Enum;

/**
 * Which way a message went.
 *
 * Inbound is from the customer, outbound is to them. Recorded rather than
 * inferred from the author, because a system-generated acknowledgement is
 * outbound with no person behind it.
 */
enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
