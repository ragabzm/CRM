<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Events;

/**
 * A ticket now exists.
 *
 * Published so that channels can acknowledge it without CreateTicket having to
 * know they exist. Tickets (T3) must not reach up to Email (T4); an event lets
 * the dependency run the right way round.
 */
final class TicketOpened
{
    public function __construct(
        public readonly string $ticketId,
        /**
         * Set when the ticket came from a machine-generated email.
         *
         * Carried on the event rather than looked up by the listener, because
         * the fact lives in headers that only the intake path ever saw — by the
         * time a listener runs, the message is a row with no trace of having
         * announced itself as automated.
         */
        public readonly bool $suppressAcknowledgement = false,
    ) {}
}
