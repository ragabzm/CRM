<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Domain;

/**
 * The three things an attachment can hang off.
 *
 * A closed set, not an open string. Polymorphism without a fixed vocabulary is
 * how a table ends up holding `Customer`, `customer` and `App\Models\Customer`
 * as three different owners of the same record.
 */
enum AttachmentOwnerType: string
{
    case Customer = 'customer';
    case Ticket = 'ticket';
    case Message = 'message';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
