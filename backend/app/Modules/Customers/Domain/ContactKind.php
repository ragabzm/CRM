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

    /**
     * A chat session, for a visitor who gave us nothing else.
     *
     * Somebody can open the widget and type a question without an address or a
     * number, and they still have to become a customer record — otherwise the
     * conversation has no owner and cannot become a ticket. The session id is
     * the only handle we have on them, so it is the identifier.
     *
     * It is deliberately NOT durable in the way an address is. A visitor who
     * comes back tomorrow with a new session is a new record, and that is the
     * honest answer: we have no way to know they are the same person. If they
     * type an email into the widget, that identifier is used instead and they
     * join the record they already had.
     */
    case Chat = 'chat';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
