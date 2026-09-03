<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

use App\Modules\Email\Jobs\SendOutboundEmailJob;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Turns a ticket message into an email on its way out.
 *
 * Everything an outbound email needs that is not in the message itself —
 * the recipient, the thread it belongs to, the subject tag, the language —
 * is assembled here, once. Doing it at each call site would mean four places
 * that each have to remember the References chain, and the one that forgets
 * breaks the customer's thread without breaking anything a test would notice.
 */
final class OutboundDispatcher
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Queues an agent's reply.
     *
     * Returns false when there is nothing to send — no channel, no address, or
     * an internal note. Internal notes are the one that matters: a note is a
     * colleague's private remark ABOUT the customer, and emailing it is the
     * failure in this whole area that cannot be taken back.
     */
    public function dispatchReply(string $messageId): bool
    {
        $message = DB::table('ticket_messages')->where('id', $messageId)->first();

        if ($message === null || $message->direction !== 'outbound') {
            return false;
        }

        $ticket = DB::table('tickets')->where('id', $message->ticket_id)->first();

        if ($ticket === null) {
            return false;
        }

        $recipient = $this->recipient((string) $ticket->customer_id);

        if ($recipient === null) {
            return false;
        }

        // The last thing anyone said on this ticket, so the customer's client
        // files this under the same conversation.
        $parent = DB::table('ticket_messages')
            ->where('ticket_id', $message->ticket_id)
            ->whereNotNull('email_message_id')
            ->where('id', '!=', $messageId)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        $headers = ThreadHeaders::forReply(
            domain: (string) $this->settings->get('email.domain'),
            ticketId: (string) $ticket->id,
            messageId: (string) $message->id,
            parentMessageId: $parent?->email_message_id,
            parentReferences: ThreadHeaders::parseReferences($parent?->email_references),
        );

        // Stored before the send, so the value a customer's client will see is
        // the value we can correlate against later — even if the send fails and
        // is retried on a different worker.
        DB::table('ticket_messages')->where('id', $messageId)->update([
            'email_message_id' => $headers->messageId,
            'email_in_reply_to' => $headers->inReplyTo,
            'email_references' => $headers->references === [] ? null : implode(' ', $headers->references),
        ]);

        SendOutboundEmailJob::dispatch(
            $recipient['address'],
            $recipient['name'],
            SubjectTagger::tag((string) $ticket->subject, (string) $ticket->reference),
            (string) $message->body,
            $headers->toArray(),
            $recipient['locale'],
            (string) $message->id,
            (string) $ticket->id,
        );

        return true;
    }

    /**
     * Queues the automatic acknowledgement for a new ticket.
     *
     * In the customer's own language, from a template an administrator can
     * edit. A reflexive English auto-reply to somebody who wrote in Arabic
     * reads as "we did not read your message".
     */
    public function dispatchAcknowledgement(string $ticketId): bool
    {
        if (! (bool) $this->settings->get('email.acknowledgement.enabled')) {
            return false;
        }

        $ticket = DB::table('tickets')->where('id', $ticketId)->first();

        if ($ticket === null) {
            return false;
        }

        $recipient = $this->recipient((string) $ticket->customer_id);

        if ($recipient === null) {
            return false;
        }

        $templates = $this->settings->get('email.acknowledgement_template');
        $body = is_array($templates)
            // English is the fallback, not an error: a missing Arabic template
            // should still acknowledge the customer.
            ? (string) ($templates[$recipient['locale']] ?? $templates['en'] ?? '')
            : '';

        if (trim($body) === '') {
            return false;
        }

        $headers = ThreadHeaders::forReply(
            domain: (string) $this->settings->get('email.domain'),
            ticketId: (string) $ticket->id,
            messageId: 'ack',
            parentMessageId: null,
            parentReferences: [],
        );

        SendOutboundEmailJob::dispatch(
            $recipient['address'],
            $recipient['name'],
            SubjectTagger::tag((string) $ticket->subject, (string) $ticket->reference),
            // The reference in the body too: a customer forwarding the
            // acknowledgement to a colleague loses the subject line more often
            // than they lose the text.
            $body."\n\n".$ticket->reference,
            $headers->toArray(),
            $recipient['locale'],
            null,
            (string) $ticket->id,
        );

        return true;
    }

    /**
     * The customer's email address, name and language.
     *
     * Read through the query builder rather than the Customers model: this is
     * Email (T4) reading from Customers (T2), and depending on that module's
     * aggregate would make this break whenever the aggregate changed.
     *
     * @return array{address: string, name: string, locale: string}|null
     */
    private function recipient(string $customerId): ?array
    {
        $customer = DB::table('customers')->where('id', $customerId)->first();

        if ($customer === null) {
            return null;
        }

        $address = DB::table('contact_identifiers')
            ->where('customer_id', $customerId)
            ->where('kind', 'email')
            ->orderByDesc('is_primary')
            ->value('value');

        if ($address === null || trim((string) $address) === '') {
            /*
             * No address is not a failure to send — it is nothing to send to.
             * Queueing a job that can only fail would fill the log with noise
             * about a customer who never gave us an email address.
             */
            return null;
        }

        return [
            'address' => (string) $address,
            'name' => (string) $customer->full_name,
            'locale' => ($customer->preferred_locale ?? 'en') === 'ar' ? 'ar' : 'en',
        ];
    }
}
