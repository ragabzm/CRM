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

    /**
     * How many of these tickets are at risk, and how many have breached.
     *
     * Null means NOT KNOWN, not zero. With the engine switched off there is
     * nothing to count, and returning 0 would be a claim — "no ticket is at
     * risk" — that a deployment with no SLA module is in no position to make.
     * An agent who read that would stop looking.
     *
     * Tallied from the same reading the list badges use, deliberately. Two
     * routes to "is this breached?" is two answers, and the one on the strip
     * would eventually disagree with the one on the row.
     *
     * @param  list<string>  $ticketIds
     * @return array{at_risk: int|null, breached: int|null}
     */
    public function countsAmong(array $ticketIds): array;

    /**
     * Which of these tickets are in a given SLA state.
     *
     * Null when the engine is off — the caller must then not filter at all,
     * rather than filter to nothing. An empty ARRAY is a real answer: nothing
     * is in that state.
     *
     * This exists because SLA state is computed, not stored: there is no
     * column to put in a WHERE clause. The cost is bounded by the caller
     * passing only the tickets it was already going to consider.
     *
     * @param  list<string>  $ticketIds
     * @return list<string>|null
     */
    public function idsInState(string $state, array $ticketIds): ?array;
}
