<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\History\TicketEventKind;

/**
 * Resolves a ticket, with a note saying what was actually done.
 *
 * The note is REQUIRED. A resolution nobody wrote down is one the next person
 * has to reconstruct from the thread — and the commonest reason a customer
 * reopens is that the fix was never explained.
 */
final class ResolveTicket
{
    public function __construct(private readonly ChangeStatus $changeStatus) {}

    public function handle(Actor $actor, string $ticketId, ?int $submittedVersion, string $resolutionNote): Ticket
    {
        return $this->changeStatus->handle(
            $actor,
            $ticketId,
            $submittedVersion,
            TicketStatus::Resolved,
            // Named specifically, so "show me everything that was resolved
            // this week" is a filter rather than a scan of status changes.
            TicketEventKind::Resolved,
            ['resolution_note' => trim($resolutionNote)],
        );
    }
}
