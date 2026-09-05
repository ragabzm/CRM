<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

enum ContactKind: string
{
    case Email = 'email';
    case Phone = 'phone';

    /**
     * A WhatsApp number, beside the phone number rather than instead of it.
     *
     * The same digits are very often both, on the same person's record — and
     * that is not a duplicate. A customer who texts from a number and messages
     * from the same one is one customer, and a reply has to go back on the
     * channel it arrived on. Collapsing the two into `phone` would make
     * "which transport does this person use?" unanswerable, and sending a
     * WhatsApp reply to somebody who only ever texted would look like the
     * business guessing.
     */
    case WhatsApp = 'whatsapp';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
