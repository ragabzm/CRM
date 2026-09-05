<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

use App\Modules\Email\Jobs\SendOutboundEmailJob;
use App\Modules\Tickets\Domain\Feedback\FeedbackInvitation;
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
    /**
     * The channels an EMAIL reply is the right answer to.
     *
     * `agent` and `system` are here because a ticket somebody raised on the
     * phone or the system opened has no other way back to the customer —
     * email is the fallback, not a guess. `web_form` too: the person gave an
     * address or a number, and where they gave an address this is how they
     * hear back. WhatsApp and SMS are deliberately absent.
     *
     * @var list<string>
     */
    private const MINE = ['email', 'agent', 'portal', 'system', 'web_form'];

    public function __construct(
        private readonly SettingsRegistry $settings,
        private readonly FeedbackInvitation $invitation,
    ) {}

    /**
     * Queues an agent's reply.
     *
     * Returns false when there is nothing to send — no channel, no address,
     * an internal note, or a ticket that arrived somewhere else. Internal
     * notes are the one that matters: a note is a colleague's private remark
     * ABOUT the customer, and emailing it is the failure in this whole area
     * that cannot be taken back.
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

        if (! in_array($ticket->channel, self::MINE, true)) {
            /*
             * A reply goes out on the channel the ticket ARRIVED on.
             *
             * Until Story 7.2 there was no other outbound transport, so this
             * dispatcher took every reply — and once WhatsApp and SMS could
             * send, a reply to a WhatsApp ticket would have gone out BOTH
             * ways: once on WhatsApp and once as an email the customer never
             * asked for, to an address they may not have given us.
             *
             * `SendReplyOnItsChannel` has the same guard in reverse, so every
             * ticket has exactly one listener that will act on it.
             */
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
            ->whereNotNull('provider_message_id')
            ->where('id', '!=', $messageId)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        $headers = ThreadHeaders::forReply(
            domain: (string) $this->settings->get('email.domain'),
            ticketId: (string) $ticket->id,
            messageId: (string) $message->id,
            parentMessageId: $parent?->provider_message_id,
            parentReferences: ThreadHeaders::parseReferences($parent?->email_references),
        );

        // Stored before the send, so the value a customer's client will see is
        // the value we can correlate against later — even if the send fails and
        // is retried on a different worker.
        DB::table('ticket_messages')->where('id', $messageId)->update([
            'provider_message_id' => $headers->messageId,
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
     * Asks the customer how it went, once, when their request is finished.
     *
     * ONE email, ever. The listener only fires on a ticket's first finish, so
     * a request that is resolved, reopened and resolved again does not ask the
     * same person the same question twice — which is the chasing the story
     * refuses by name. There is no reminder, no second attempt, and nothing
     * that runs on a schedule looking for people who have not replied.
     *
     * The two links are the whole invitation. Tapping one records the answer;
     * that is the entire cost of replying, and it is why this is worth sending
     * at all. Nobody fills in a survey about a support ticket.
     */
    public function dispatchFeedbackInvitation(string $ticketId): bool
    {
        if (! (bool) $this->settings->get('email.feedback_invitation.enabled')) {
            return false;
        }

        $ticket = DB::table('tickets')->where('id', $ticketId)->first();

        if ($ticket === null) {
            return false;
        }

        /*
         * Same channel guard as a reply, and for the same reason. Somebody who
         * asked on WhatsApp gets asked on WhatsApp or in the portal; emailing
         * them as well is the double-notification this guard was added to stop.
         */
        if (! in_array($ticket->channel, self::MINE, true)) {
            return false;
        }

        $recipient = $this->recipient((string) $ticket->customer_id);

        if ($recipient === null) {
            return false;
        }

        $locale = $recipient['locale'];

        $templates = $this->settings->get('email.feedback_invitation_template');
        $intro = is_array($templates)
            // English is the fallback, not an error: a missing Arabic template
            // should still ask the question.
            ? (string) ($templates[$locale] ?? $templates['en'] ?? '')
            : '';

        if (trim($intro) === '') {
            return false;
        }

        $links = $this->invitation->linksFor($ticketId);

        $body = implode("\n", [
            $intro,
            '',
            __('emails.feedback.good', [], $locale).': '.$links[FeedbackInvitation::UP],
            __('emails.feedback.bad', [], $locale).': '.$links[FeedbackInvitation::DOWN],
            '',
            // Said out loud, because a link that has quietly stopped working is
            // read as the product ignoring them.
            __('emails.feedback.expiry', ['hours' => (string) $this->invitation->lifetimeHours()], $locale),
            '',
            (string) $ticket->reference,
        ]);

        $headers = ThreadHeaders::forReply(
            domain: (string) $this->settings->get('email.domain'),
            ticketId: (string) $ticket->id,
            // In the SAME thread as the conversation it is about. A separate
            // thread reads as a marketing email and is deleted unread.
            messageId: 'feedback',
            parentMessageId: null,
            parentReferences: [],
        );

        SendOutboundEmailJob::dispatch(
            $recipient['address'],
            $recipient['name'],
            SubjectTagger::tag((string) $ticket->subject, (string) $ticket->reference),
            $body,
            $headers->toArray(),
            $locale,
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
