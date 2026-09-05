<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Events;

/**
 * A ticket has reached a finished state for the FIRST time.
 *
 * Announced once per ticket in its whole life. A ticket that is resolved,
 * reopened and resolved again does not announce this twice, because the thing
 * listeners do with it — ask the customer how it went — is a thing you get to
 * do once. Asking again is chasing, and somebody who ignored the first email
 * has already answered.
 *
 * Carries the status it reached, because "resolved" and "closed" read
 * differently to the person receiving the message: one says we think we are
 * done, the other says we have stopped.
 */
final class TicketFinished
{
    public function __construct(
        public readonly string $ticketId,
        /** `resolved` or `closed`. */
        public readonly string $status,
    ) {}
}
