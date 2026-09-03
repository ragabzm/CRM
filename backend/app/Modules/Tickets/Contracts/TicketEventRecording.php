<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Contracts;

/**
 * How a module outside Tickets appends one fact to a ticket's history.
 *
 * Primitives only, no domain objects. The first draft took a `TicketEventKind`
 * and an `Actor` — and `ContractsPurityTest` refused it, correctly: a contract
 * that passes domain objects drags every caller into Tickets' domain, which is
 * the coupling this interface exists to avoid. Sla should need to know a string
 * and a reason, not a class hierarchy.
 *
 * System actors only, deliberately. The only caller outside Tickets is the SLA
 * breach detector, and a breach is never something a person did. An interface
 * that also accepted a user id would be an invitation for another module to
 * write history on someone's behalf.
 */
interface TicketEventRecording
{
    /**
     * Appends one system-attributed event.
     *
     * Must be called inside a transaction; the implementation refuses
     * otherwise, so the event and whatever caused it succeed or fail together.
     *
     * @param  string  $kind    A `TicketEventKind` backing value, e.g. `ticket.sla_breached`.
     * @param  string  $reason  Why the system acted, e.g. `sla_breach`.
     * @param  array<string, mixed>|null  $before  Changed fields only.
     * @param  array<string, mixed>|null  $after   Changed fields only.
     * @param  array<string, mixed>|null  $meta
     */
    public function recordSystemEvent(
        string $ticketId,
        string $kind,
        string $reason,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
    ): void;
}
