<?php

declare(strict_types=1);

namespace App\Modules\Email\Listeners;

use App\Modules\Email\Domain\OutboundDispatcher;
use App\Modules\Tickets\Domain\Events\AgentReplyPosted;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Puts an agent's reply on its way to the customer.
 *
 * A listener rather than a call inside the command: Tickets (T3) must not reach
 * up to Email (T4), and an event lets the dependency run the right way round.
 * It also means the reply is written whether or not the channel is configured —
 * a broken mail provider is not allowed to refuse an agent's words.
 */
final class SendAgentReply
{
    public function __construct(private readonly OutboundDispatcher $dispatcher) {}

    public function handle(AgentReplyPosted $event): void
    {
        try {
            $this->dispatcher->dispatchReply($event->messageId);
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
            Log::warning('Could not queue the outbound reply.', [
                'message_id' => $event->messageId,
                'reason' => $e->getMessage(),
                'consequence' => 'The ticket is unaffected; nothing was emailed.',
            ]);
        }
    }
}
