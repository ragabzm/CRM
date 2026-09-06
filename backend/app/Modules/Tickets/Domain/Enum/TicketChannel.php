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
    /**
     * The public web form.
     *
     * Its own value rather than folding into `portal`: a portal request comes
     * from somebody who signed in and whose identity we know, and a form comes
     * from a stranger. An agent reading a queue needs to be able to tell those
     * apart before they answer.
     */
    case WebForm = 'web_form';

    /**
     * WhatsApp and SMS, which are two channels and one implementation.
     *
     * Separate values rather than one `phone`, because a reply has to go back
     * the way it came: somebody holding WhatsApp is not reachable by text
     * unless they said so, and the ticket is the only record of which they
     * used.
     */
    case WhatsApp = 'whatsapp';

    case Sms = 'sms';

    /**
     * A conversation in the chat widget.
     *
     * Its own value rather than folding into `web_form`, and for the reason
     * that decides every value on this enum: a reply has to go back the way it
     * came. A chat visitor is reading a widget that polls, not an inbox — an
     * emailed reply to a chat conversation reaches somebody who may have given
     * no address at all, and would arrive hours after they closed the tab.
     */
    case Chat = 'chat';

    case System = 'system';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
