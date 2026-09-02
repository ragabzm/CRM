<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

enum ContactKind: string
{
    case Email = 'email';
    case Phone = 'phone';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
