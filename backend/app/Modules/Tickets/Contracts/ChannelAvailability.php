<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Contracts;

/**
 * How Tickets asks "can we still send on the channel this arrived by?".
 *
 * Declared HERE, in Tickets, and implemented by Channels — not the other way
 * round. Channels is T4 and Tickets is T3, so Tickets must not depend on it at
 * all: not on its classes, and not on an interface in its namespace either.
 * Putting the contract with the CONSUMER and the implementation with the
 * provider is what turns an upward dependency into a downward one, and it is
 * the same shape `SlaReader` already uses.
 *
 * A deployment with no channel accounts configured binds nothing, and the
 * default below answers "open" for everything — which is correct: there is no
 * account, so nothing has been switched off.
 *
 * Primitives only, so no caller learns a domain type.
 */
interface ChannelAvailability
{
    /**
     * Whether any account on this channel still accepts outbound.
     *
     * Asked once per channel and not once per ticket, because a ticket list
     * renders fifty rows across four channels and a per-row query would be the
     * N+1 this signature exists to prevent. Implementations memoise per
     * request, never per process — a channel disabled in one request must not
     * still look disabled in the next.
     */
    public function isOpen(string $channel): bool;
}
