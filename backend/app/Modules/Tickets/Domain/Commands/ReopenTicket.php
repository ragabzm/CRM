<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;

/**
 * Reopens a resolved or closed ticket.
 *
 * Moves to `open`, not to a status of its own. A reopened ticket IS open — it
 * needs someone to work it and belongs in the open queue — and a fifth status
 * would mean every "needs attention" filter had to remember to include it or
 * silently miss the tickets that came back.
 *
 * That it came back is recorded as a `ticket.reopened` EVENT, which is where
 * that fact belongs: in the history, not in the current state.
 */
final class ReopenTicket
{
    public function __construct(private readonly ChangeStatus $changeStatus) {}

    public function handle(Actor $actor, string $ticketId, ?int $submittedVersion, ?string $reason = null): Ticket
    {
        return $this->changeStatus->handle(
            $actor,
            $ticketId,
            $submittedVersion,
            TicketStatus::Open,
            TicketEvent::REOPENED,
            $reason !== null ? ['reason' => trim($reason)] : [],
        );
    }
}
