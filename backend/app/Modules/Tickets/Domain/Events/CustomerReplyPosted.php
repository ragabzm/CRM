<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Events;

/**
 * A customer said something on a ticket.
 *
 * Declared here, fired elsewhere: the conversation surface that actually
 * receives customer replies is a later story. Publishing the event now means
 * that story wires one dispatch call rather than also having to discover that
 * resolved tickets are supposed to reopen.
 */
final class CustomerReplyPosted
{
    public function __construct(
        public readonly string $ticketId,
        public readonly ?string $messageId = null,
    ) {}
}
