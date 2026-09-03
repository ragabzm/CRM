<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Events;

/**
 * An agent said something to a customer on a ticket.
 *
 * Fired for outbound messages only — never for an internal note. That
 * distinction is enforced where the event is DISPATCHED rather than where it is
 * handled, so a future listener cannot accidentally email a colleague's private
 * remark by forgetting to check the direction.
 */
final class AgentReplyPosted
{
    public function __construct(
        public readonly string $ticketId,
        public readonly string $messageId,
    ) {}
}
