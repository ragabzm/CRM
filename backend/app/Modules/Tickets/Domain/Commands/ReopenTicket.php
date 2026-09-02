<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;

/**
 * Reopens a resolved ticket.
 *
 * Moves to `reopened` rather than back to `open`, because the two are different
 * facts: a reopened ticket is one where the first attempt did not work, and
 * collapsing it into `open` loses the only signal that would tell anyone.
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
            TicketStatus::Reopened,
            TicketEvent::REOPENED,
            $reason !== null ? ['reason' => trim($reason)] : [],
        );
    }
}
