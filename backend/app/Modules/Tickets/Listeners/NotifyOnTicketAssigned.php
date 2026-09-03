<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Listeners;

use App\Modules\Tickets\Notifications\TicketAssigned as TicketAssignedNotification;
use App\Modules\Tickets\Notifications\TicketNotifier;
use App\Modules\Tickets\Domain\Events\TicketAssigned;

/**
 * Tells somebody a ticket is now theirs.
 */
final class NotifyOnTicketAssigned
{
    public function __construct(private readonly TicketNotifier $notifier) {}

    public function handle(TicketAssigned $event): void
    {
        $facts = $this->notifier->ticketFacts($event->ticketId);

        if ($facts === null) {
            return;
        }

        $this->notifier->notifyAssignee(
            $event->assigneeId,
            $event->actorId,
            new TicketAssignedNotification(
                $facts['id'],
                $facts['reference'],
                $facts['subject'],
                $event->actorName,
            ),
        );
    }
}
