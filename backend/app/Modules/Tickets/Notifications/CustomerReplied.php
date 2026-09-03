<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

/**
 * "They have answered you."
 *
 * The trigger that most often decides whether a ticket moves today. A reply on
 * a ticket an agent asked a question on sits in the queue unnoticed otherwise —
 * the ticket looks unchanged from the outside, and the agent has no reason to
 * open it again.
 */
final class CustomerReplied extends TicketNotification
{
    protected function key(): string
    {
        return 'notifications.customer_replied';
    }
}
