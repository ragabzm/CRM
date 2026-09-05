<?php

declare(strict_types=1);

namespace App\Modules\Email\Listeners;

use App\Modules\Email\Domain\OutboundDispatcher;
use App\Modules\Tickets\Domain\Events\TicketFinished;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "How did it go?" — asked once, when the work is finished.
 *
 * Listens to the FIRST finish and nothing else, so this cannot become the
 * feature that emails somebody every time an agent presses Resolve on a
 * conversation that keeps coming back.
 */
final class InviteFeedback
{
    public function __construct(private readonly OutboundDispatcher $dispatcher) {}

    public function handle(TicketFinished $event): void
    {
        try {
            $this->dispatcher->dispatchFeedbackInvitation($event->ticketId);
        } catch (Throwable $e) {
            /*
             * Never back out of the resolution.
             *
             * On a synchronous queue the send runs inline, so an unreachable
             * provider would propagate out of the command that resolved the
             * ticket — refusing to finish a customer's request because an SMTP
             * host is down. The work is recorded either way; asking how it went
             * is a separate promise and its failure belongs in the log.
             */
            Log::warning('Could not queue the feedback invitation.', [
                'ticket_id' => $event->ticketId,
                'reason' => $e->getMessage(),
                'consequence' => 'The ticket is resolved; nothing was emailed. The portal still asks.',
            ]);
        }
    }
}
