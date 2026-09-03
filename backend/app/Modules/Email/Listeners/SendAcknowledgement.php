<?php

declare(strict_types=1);

namespace App\Modules\Email\Listeners;

use App\Modules\Email\Domain\OutboundDispatcher;
use App\Modules\Tickets\Domain\Events\TicketOpened;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the customer their request was received.
 *
 * The silence between raising a request and an agent picking it up is where
 * people give up and phone instead — or raise the same ticket twice. The
 * acknowledgement is short, in their own language, and carries the reference
 * they will need if they do call.
 */
final class SendAcknowledgement
{
    public function __construct(private readonly OutboundDispatcher $dispatcher) {}

    public function handle(TicketOpened $event): void
    {
        if ($event->suppressAcknowledgement) {
            /*
             * A mail loop, stopped here.
             *
             * The ticket was opened by something that announced itself as
             * machine-generated — an out-of-office, a mailing list, a bounce.
             * Acknowledging it would produce a reply to a robot that replies to
             * everything, and the two would keep each other busy until somebody
             * noticed the mailbox.
             */
            return;
        }

        try {
            $this->dispatcher->dispatchAcknowledgement($event->ticketId);
        } catch (Throwable $e) {
            /*
             * A failure here must never escape into whatever caused the event.
             *
             * On a synchronous queue the send runs inline, so an unreachable
             * provider would propagate all the way back out of the command that
             * fired this — and refusing to create a ticket because an SMTP host
             * is down is exactly the coupling this whole story is meant to
             * prevent. The work is recorded either way; the email is a separate
             * promise, and its failure belongs in the log and the mail log.
             */
            Log::warning('Could not queue the outbound acknowledgement.', [
                'ticket_id' => $event->ticketId,
                'reason' => $e->getMessage(),
                'consequence' => 'The ticket is unaffected; nothing was emailed.',
            ]);
        }
    }
}
