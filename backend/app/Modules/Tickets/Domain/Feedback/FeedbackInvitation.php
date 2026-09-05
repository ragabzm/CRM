<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Feedback;

use App\Modules\Tickets\Domain\Commands\RateTicket;
use Illuminate\Support\Facades\URL;

/**
 * The one-tap link that goes in the resolution email.
 *
 * Two links, one per answer, so that saying how it went from an inbox costs
 * exactly what it costs in the portal: a single tap, no sign-in, no password
 * the customer never set, no page that asks who they are before letting them
 * answer a question about their own request.
 *
 * The AUTHORISATION is the signature and nothing else. It is scoped to one
 * ticket id and one verdict, both inside the signed payload, so it cannot be
 * edited into a link that rates somebody else's request — and it grants
 * nothing at all beyond recording that answer. It is not a session: following
 * it does not show the conversation, the customer's other requests, or
 * anything a portal sign-in would show.
 *
 * It EXPIRES, because a rating link that works for ever is a permanent
 * unauthenticated write against a ticket sitting in an inbox that may later
 * belong to somebody else.
 */
final class FeedbackInvitation
{
    public const ROUTE = 'tickets.feedback.invitation';

    /** The two answers, as they appear in the URL. There is no third. */
    public const UP = 'up';

    public const DOWN = 'down';

    public function __construct(private readonly RateTicket $rate) {}

    /**
     * Signed links for both answers, valid from now.
     *
     * @return array{up: string, down: string}
     */
    public function linksFor(string $ticketId): array
    {
        $expiry = now()->addHours($this->lifetimeHours());

        return [
            self::UP => URL::temporarySignedRoute(self::ROUTE, $expiry, [
                'ticket' => $ticketId,
                'verdict' => self::UP,
            ]),
            self::DOWN => URL::temporarySignedRoute(self::ROUTE, $expiry, [
                'ticket' => $ticketId,
                'verdict' => self::DOWN,
            ]),
        ];
    }

    /**
     * How long the link lives: the rating change window.
     *
     * Deliberately the same number, so that "how long can I change my mind"
     * has one answer wherever it is asked. A link that outlived the window
     * would land the customer on a page that refuses them, having invited
     * them.
     *
     * Floored at an hour. The window is allowed to be zero, which means a
     * rating locks the instant it is given — a rule about CHANGES, not about
     * the first answer. Signing a link that has already expired would post an
     * invitation nobody could ever accept.
     */
    public function lifetimeHours(): int
    {
        return max(1, $this->rate->windowHours());
    }

    public static function isPositive(string $verdict): bool
    {
        return $verdict === self::UP;
    }
}
