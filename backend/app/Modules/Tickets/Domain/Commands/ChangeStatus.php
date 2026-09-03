<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\Ticket;

/**
 * Moves a ticket along its lifecycle. The transition rules live on the enum.
 */
final class ChangeStatus
{
    public function __construct(private readonly UpdateTicketAttributes $update) {}

    /**
     * @param  array<string, mixed>  $payload  Extra facts for the event.
     */
    public function handle(
        Actor $actor,
        string $ticketId,
        ?int $submittedVersion,
        TicketStatus $status,
        ?TicketEventKind $event = null,
        array $payload = [],
    ): Ticket {
        return $this->update->handle(
            $actor,
            $ticketId,
            $submittedVersion,
            TicketAttributeChanges::of(['status' => $status]),
            $event,
            $payload,
        );
    }
}
