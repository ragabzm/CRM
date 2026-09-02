<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Listeners;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\ChangeStatus;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Events\CustomerReplyPosted;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A customer replying to a resolved ticket means it was not resolved.
 *
 * They are the only party who can really say so, and making them wait for an
 * agent to notice is how a "resolved" ticket sits for three days while the
 * customer assumes somebody is reading.
 */
final class ReopenOnCustomerReply
{
    public function __construct(private readonly ChangeStatus $changeStatus) {}

    public function handle(CustomerReplyPosted $event): void
    {
        $ticket = Ticket::query()->find($event->ticketId);

        if ($ticket === null) {
            return;
        }

        /*
         * Stamped FIRST, and always — even when the status does not change.
         *
         * The auto-close sweep reads this column to decide whether the customer
         * has gone quiet. Recording the reply after the transition would leave
         * a window in which a sweep sees an old resolved_at, no recent
         * activity, and closes a ticket the customer has just written on.
         */
        $ticket->forceFill(['last_customer_activity_at' => now()])->save();

        if ($ticket->status !== TicketStatus::Resolved) {
            /*
             * Only `resolved` reopens automatically. A CLOSED ticket needs a
             * person: closing is an agreed ending, and a stray "thanks!" three
             * weeks later should not silently restart it.
             */
            return;
        }

        try {
            $this->changeStatus->handle(
                Actor::system('customer_reply'),
                (string) $ticket->getKey(),
                // Exempt from the version check: the customer is not editing a
                // form, and has no version to be stale against.
                null,
                TicketStatus::Open,
            );
        } catch (Throwable $e) {
            // The reply is already recorded and is the thing that matters; a
            // failed reopen must not lose it.
            Log::warning('tickets.reopen_on_reply.failed', [
                'ticket_id' => (string) $ticket->getKey(),
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
