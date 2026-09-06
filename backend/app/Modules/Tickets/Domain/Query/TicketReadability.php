<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Query;

use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * "May this person read this ticket?", asked from outside Tickets.
 *
 * The row-level rule lives in `TicketVisibility` and is applied at each query
 * site. A module ABOVE Tickets that needs to answer the same question — the
 * assists, which summarise a conversation — must not reach for the aggregate
 * to do it: `NoCrossModuleModelsTest` refuses that, and rightly, because a
 * caller holding another module's model is a caller that can write with it.
 *
 * So the question is answered here, once, by the module that owns the rule.
 * The alternative is every caller reimplementing "assigned to me, or in my
 * department, or unassigned" — and the copy that is wrong is the one nobody
 * tested.
 */
final class TicketReadability
{
    public static function canRead(Authenticatable $actor, string $ticketId): bool
    {
        return TicketVisibility::scopeForActor(Ticket::query()->whereKey($ticketId), $actor)->exists();
    }
}
