<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Ticket;

/**
 * Hands a ticket to someone, or back to the pool.
 *
 * A named command rather than a generic attribute update, because "who is
 * holding this?" is the question the workspace is built around and a caller
 * should be able to express it directly.
 */
final class AssignTicket
{
    public function __construct(private readonly UpdateTicketAttributes $update) {}

    /** @param int|null $assigneeId Null returns the ticket to the unassigned pool. */
    public function handle(Actor $actor, string $ticketId, ?int $submittedVersion, ?int $assigneeId): Ticket
    {
        return $this->update->handle(
            $actor,
            $ticketId,
            $submittedVersion,
            TicketAttributeChanges::of(['assignee_id' => $assigneeId]),
        );
    }
}
