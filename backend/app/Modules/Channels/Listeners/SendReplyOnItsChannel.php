<?php

declare(strict_types=1);

namespace App\Modules\Channels\Listeners;

use App\Modules\Channels\Adapters\PhoneChannelAdapter;
use App\Modules\Channels\Jobs\SendChannelMessageJob;
use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Tickets\Domain\Events\AgentReplyPosted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * An agent's reply goes out on the channel the ticket arrived on.
 *
 * Not on a channel the agent chose, and not on email by default: somebody who
 * messaged from WhatsApp is holding WhatsApp, and answering them by email is
 * answering somewhere they are not looking.
 *
 * Listens to the same event the mail listener does, and the two do not
 * overlap — this one returns immediately for a ticket that arrived any other
 * way. Email's own listener does the same in reverse. That is why "which
 * channel does this reply go out on?" has one answer per ticket rather than a
 * race between two listeners.
 */
final class SendReplyOnItsChannel
{
    /** The channels this listener is responsible for. */
    private const MINE = [PhoneChannelAdapter::WHATSAPP, PhoneChannelAdapter::SMS];

    public function handle(AgentReplyPosted $event): void
    {
        try {
            $this->queue($event->messageId);
        } catch (Throwable $e) {
            /*
             * A failure here must never escape into whatever caused the event.
             *
             * On a synchronous queue the send runs inline, so an unreachable
             * gateway would propagate back out of the command that fired this
             * — and refusing to record a reply because a provider is down is
             * exactly the coupling the channel spine exists to prevent. The
             * reply is written either way; delivering it is a separate promise.
             */
            Log::warning('Could not queue the outbound channel reply.', [
                'message_id' => $event->messageId,
                'reason' => $e->getMessage(),
                'consequence' => 'The reply is recorded; nothing was sent.',
            ]);
        }
    }

    private function queue(string $messageId): void
    {
        $message = DB::table('ticket_messages')->where('id', $messageId)->first();

        if ($message === null || $message->direction !== 'outbound') {
            return;
        }

        $ticket = DB::table('tickets')->where('id', $message->ticket_id)->first();

        if ($ticket === null || ! in_array($ticket->channel, self::MINE, true)) {
            // Not this listener's channel. Email's listener has the same
            // guard in reverse.
            return;
        }

        $to = $this->numberFor((string) $ticket->customer_id, (string) $ticket->channel);

        if ($to === null) {
            /*
             * Recorded as failed rather than silently dropped. A reply nobody
             * could deliver has to be visible in the timeline with Retry —
             * UX-09 — because the alternative is an agent believing they
             * answered.
             */
            DB::table('ticket_messages')->where('id', $messageId)->update([
                'delivery_state' => 'failed',
                'updated_at' => now(),
            ]);

            Log::warning('No number on record for this channel.', [
                'message_id' => $messageId,
                'channel' => $ticket->channel,
            ]);

            return;
        }

        DB::table('ticket_messages')->where('id', $messageId)->update([
            'delivery_state' => 'queued',
            'updated_at' => now(),
        ]);

        SendChannelMessageJob::dispatch(
            $messageId,
            (string) $ticket->channel,
            $this->accountFor((string) $ticket->channel, (string) $message->ticket_id),
            $to,
            (string) $message->body,
        );
    }

    /**
     * The customer's number FOR THIS CHANNEL.
     *
     * A WhatsApp reply goes to their WhatsApp identifier and an SMS to their
     * phone one, even where the digits are identical — because they may not
     * be, and guessing which is worse than the ticket saying it could not be
     * delivered.
     */
    private function numberFor(string $customerId, string $channel): ?string
    {
        $kind = $channel === PhoneChannelAdapter::WHATSAPP
            ? ContactKind::WhatsApp->value
            : ContactKind::Phone->value;

        $value = DB::table('contact_identifiers')
            ->where('customer_id', $customerId)
            ->where('kind', $kind)
            ->orderByDesc('is_primary')
            ->value('value_normalised');

        return $value === null ? null : (string) $value;
    }

    /** The account this ticket's inbound message came in on, if it is known. */
    private function accountFor(string $channel, string $ticketId): ?string
    {
        $fromInbound = DB::table('inbound_messages')
            ->where('ticket_id', $ticketId)
            ->whereNotNull('channel_account_id')
            ->value('channel_account_id');

        if ($fromInbound !== null) {
            return (string) $fromInbound;
        }

        // Falls back to the one active account on the channel, which is the
        // only unambiguous answer when the ticket did not arrive on one.
        $id = DB::table('channel_accounts')
            ->where('channel', $channel)
            ->where('is_active', true)
            ->value('id');

        return $id === null ? null : (string) $id;
    }
}
