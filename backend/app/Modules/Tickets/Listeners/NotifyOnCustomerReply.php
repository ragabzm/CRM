<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Listeners;

use App\Modules\Tickets\Notifications\CustomerReplied;
use App\Modules\Tickets\Notifications\TicketNotifier;
use App\Modules\Tickets\Domain\Events\CustomerReplyPosted;

/**
 * Tells the assignee their customer has answered.
 *
 * The trigger that most often decides whether a ticket moves today: a reply on
 * a ticket an agent asked a question on sits in the queue unnoticed otherwise,
 * because the ticket looks unchanged from the outside.
 */
final class NotifyOnCustomerReply
{
    public function __construct(private readonly TicketNotifier $notifier) {}

    public function handle(CustomerReplyPosted $event): void
    {
        $facts = $this->notifier->ticketFacts($event->ticketId);

        if ($facts === null) {
            return;
        }

        /*
         * No actor to exclude: the customer is not a staff account, so the
         * assignee is never the person who did this.
         */
        $this->notifier->notifyAssignee(
            $facts['assignee_id'],
            null,
            new CustomerReplied($facts['id'], $facts['reference'], $facts['subject']),
        );
    }
}
