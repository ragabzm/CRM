<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Contracts;

/**
 * How Tickets asks "where does this stand against its targets?".
 *
 * Declared HERE, in Tickets, and implemented by Sla — not the other way round.
 *
 * Sla is T4 and Tickets is T3, so Tickets must not depend on Sla at all: not on
 * its classes, and not on an interface in its namespace either. Putting the
 * contract with the CONSUMER and the implementation with the provider is what
 * turns an upward dependency into a downward one, and it is the same shape
 * `DepartmentUsageProbe` and `CategoryUsageProbe` already use.
 *
 * It also means a deployment with the SLA engine switched off binds the null
 * implementation and nothing in Tickets knows there is anything to switch off.
 *
 * Primitives only, so no caller learns a domain type.
 */
interface SlaReader
{
    /**
     * SLA blocks for a set of tickets, keyed by ticket id.
     *
     * Batched rather than one at a time: a ticket list reads twenty-five of
     * these at once, and a per-ticket call would be the N+1 this signature
     * exists to prevent.
     *
     * @param  list<string>  $ticketIds
     * @return array<string, array<string, mixed>>
     */
    public function forTickets(array $ticketIds): array;
}
