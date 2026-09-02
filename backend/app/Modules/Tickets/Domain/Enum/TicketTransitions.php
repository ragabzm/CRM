<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Enum;

/**
 * The lifecycle graph. One table, one place.
 *
 * Referenced by the command handler and by nothing else — not the controllers,
 * not the UI. A second copy anywhere would be the copy that says yes when this
 * one says no, and the UI's copy is the one users would find first.
 *
 * The edges, and why each exists:
 *
 *   open ⇄ pending      work is waiting on someone else, and comes back
 *   open → resolved     the ordinary finish
 *   pending → resolved  finished while waiting; the wait stopped mattering
 *   resolved → closed   the sweep, or a person, agreeing it is really done
 *   resolved → open     the customer replied — they disagree it is done
 *   closed → open       reopened by hand, inside the reopen window only
 *
 * Everything else is false, including `closed → resolved` and `closed →
 * pending`: a closed ticket comes back to `open` and starts again, or it stays
 * closed. Letting it re-enter mid-lifecycle would produce tickets that were
 * never open in their own history.
 */
final class TicketTransitions
{
    public static function allowed(TicketStatus $from, TicketStatus $to): bool
    {
        if ($from === $to) {
            // Setting a status to what it already is changes nothing. Refusing
            // it would make a retried request fail, which is the opposite of
            // what a retry should do.
            return true;
        }

        return match ($from) {
            TicketStatus::Open => in_array($to, [TicketStatus::Pending, TicketStatus::Resolved], true),
            TicketStatus::Pending => in_array($to, [TicketStatus::Open, TicketStatus::Resolved], true),
            TicketStatus::Resolved => in_array($to, [TicketStatus::Closed, TicketStatus::Open], true),
            TicketStatus::Closed => $to === TicketStatus::Open,
        };
    }

    /**
     * Where a ticket in this status may go next.
     *
     * @return list<TicketStatus>
     */
    public static function from(TicketStatus $status): array
    {
        return array_values(array_filter(
            TicketStatus::cases(),
            static fn (TicketStatus $to): bool => $to !== $status && self::allowed($status, $to),
        ));
    }
}
