<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

/**
 * "This is now yours."
 *
 * The one notification with a named actor, because the answer to "who gave me
 * this?" is usually the next question — and a ticket that arrives from nobody
 * reads as the system doing something to you rather than a colleague asking.
 */
final class TicketAssigned extends TicketNotification
{
    public function __construct(
        string $ticketId,
        string $reference,
        string $subject,
        private readonly string $actorName,
    ) {
        parent::__construct($ticketId, $reference, $subject);
    }

    protected function key(): string
    {
        return 'notifications.assigned';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return [...parent::replacements($notifiable), 'actor' => $this->actorName];
    }
}
