<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

/**
 * A supervisor is told a ticket is going wrong, and why.
 *
 * The REASON travels in the notification, not just a link to go and find it.
 * "TKT-000042 was escalated" is an alarm; "TKT-000042 was escalated by Dana
 * Faris — the customer has been waiting four days for a part that was never
 * ordered" is something a supervisor can act on from their phone. The whole
 * point of escalating is that attention arrives before the customer has to ask
 * for it, and an alert that requires opening the ticket to understand has
 * spent the time it was meant to save.
 */
final class TicketEscalated extends TicketNotification
{
    public function __construct(
        string $ticketId,
        string $reference,
        string $subject,
        /** Who raised it. The system's own name when a missed target did. */
        private readonly string $escalatedBy,
        private readonly string $reason,
    ) {
        parent::__construct($ticketId, $reference, $subject);
    }

    protected function key(): string
    {
        return 'notifications.escalated';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return [
            ...parent::replacements($notifiable),
            'by' => $this->escalatedBy,
            /*
             * The reason as it was written, never translated. It is a
             * colleague's own words about this particular ticket, and running
             * it through anything would be putting words in their mouth.
             */
            'reason' => $this->reason,
        ];
    }
}
