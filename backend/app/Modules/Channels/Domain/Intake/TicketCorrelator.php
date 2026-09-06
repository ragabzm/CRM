<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Intake;

use App\Modules\Channels\Contracts\ChannelPayload;
use App\Modules\Channels\Domain\TicketReference;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use Illuminate\Support\Facades\DB;

/**
 * Which ticket this message belongs to, and how we decided.
 *
 * ONE correlator for every channel. An adapter supplies a `ChannelPayload` and
 * nothing else; it does not get to bring its own matching rules. A second
 * correlator is how two channels start answering "is this a reply?" differently
 * for the same customer, and the divergence is invisible until somebody
 * compares two tickets side by side.
 *
 * The rules, in descending order of how much the sender's client had to get
 * right:
 *
 *   1. thread rules    — the transport threaded properly. Almost always right.
 *                        The adapter names them; mail supplies `in_reply_to`
 *                        then `references`, and those names are what the trace
 *                        records.
 *   2. `subject_token` — the headers were stripped, by a webmail rewriting the
 *                        message or by a customer replying from a fresh email.
 *   3. `body_token`    — the subject was retyped too.
 *   4. `open_ticket`   — no reference anywhere, but this identifier has one
 *                        open ticket inside the channel's window.
 *
 * Then: a new ticket.
 *
 * Rule 4 needs its history stated, because it looks like a mistake.
 *
 * It was deliberately NOT a rule when this correlator only served email, and
 * the reason still stands for email: a customer with three open tickets who
 * writes about something new would have it attached to whichever ticket
 * happened to be newest, and the mistake would be invisible to everyone
 * including them. That is why the rule is governed by a per-channel window and
 * why the EMAIL window defaults to zero — off. Email keeps the behaviour it
 * has, byte for byte, and its test suite proves it.
 *
 * It exists because a transport with no threading needs it. A web form carries
 * no headers and no reference; without rule 4 every reply a customer types into
 * the form becomes a brand-new ticket, and the conversation splits into a pile
 * of one-message tickets. Two further limits keep it honest: it fires only when
 * the identifier has EXACTLY ONE open ticket in the window, and never when the
 * message carried a reference that failed to match — because a reference that
 * points nowhere means the customer is telling us something we should not
 * override with a guess.
 *
 * Every attempt is recorded, not just the winner. "Why did this land on that
 * ticket?" is the question a mis-correlated message raises, and without the
 * trace the only way to answer is to re-run the logic against a message that
 * has since been consumed.
 */
final class TicketCorrelator
{
    /**
     * @param  int  $windowHours  How far back rule 4 looks. Zero disables it.
     */
    public function correlate(
        ChannelPayload $payload,
        int $windowHours,
        ?string $knownTicketId = null,
    ): CorrelationResult {
        if ($knownTicketId !== null) {
            /*
             * The transport KNOWS which conversation this is.
             *
             * Chat is the first such channel: a widget holds a token scoped to
             * exactly one conversation, and that conversation has exactly one
             * ticket. There is nothing to infer, and running the heuristics
             * anyway would mean a chat message could be matched onto a
             * DIFFERENT ticket by a subject token the visitor happened to type.
             *
             * Here rather than in the chat controller, so it stays visible in
             * the same trace as every other rule and there is still exactly
             * one correlator. A second one is how two channels start answering
             * "is this a reply?" differently for the same customer.
             */
            return new CorrelationResult(
                $knownTicketId,
                'known_conversation',
                [['rule' => 'known_conversation', 'matched' => true]],
            );
        }

        $trace = [];
        $quotedReference = $this->quotedReference($payload);

        foreach ($this->rules($payload, $quotedReference, $windowHours) as $rule => $attempt) {
            $ticketId = $attempt();

            $trace[] = ['rule' => $rule, 'matched' => $ticketId !== null];

            if ($ticketId !== null) {
                return new CorrelationResult($ticketId, $rule, $trace);
            }
        }

        $trace[] = ['rule' => 'new_ticket', 'matched' => true];

        return new CorrelationResult(null, 'new_ticket', $trace);
    }

    /**
     * The chain, in order, with the rules that do not apply left out entirely.
     *
     * Omitted rather than attempted-and-failed: a trace listing rules that
     * could never have fired reads as though they were tried, which is exactly
     * the misleading history this class exists to prevent.
     *
     * @return array<string, callable(): ?string>
     */
    private function rules(ChannelPayload $payload, ?string $quotedReference, int $windowHours): array
    {
        $rules = [];

        foreach ($payload->threadRules as $name => $ids) {
            $rules[$name] = fn (): ?string => $this->byThreadId($ids);
        }

        $rules['subject_token'] = fn (): ?string => $this->byReference(TicketReference::inText($payload->subject));
        $rules['body_token'] = fn (): ?string => $this->byReference(TicketReference::inText($payload->body));

        /*
         * Not attempted when the customer quoted a reference we could not
         * resolve. They named a ticket; guessing a different one would be
         * overriding an explicit answer with an inferred one.
         */
        if ($windowHours > 0 && $quotedReference === null) {
            $rules['open_ticket'] = fn (): ?string => $this->byOpenTicket($payload, $windowHours);
        }

        return $rules;
    }

    /** A reference the sender quoted anywhere, resolvable or not. */
    private function quotedReference(ChannelPayload $payload): ?string
    {
        return TicketReference::inText($payload->subject) ?? TicketReference::inText($payload->body);
    }

    /**
     * Finds the ticket that sent one of these provider message ids.
     *
     * Reads `provider_message_id`, written at the moment a reply goes out —
     * the value the customer's client or the provider actually saw, rather
     * than one derived again later and possibly differently. Every transport
     * writes it: a mail Message-ID, a WhatsApp message id, an SMS SID.
     *
     * @param  list<string>  $threadIds
     */
    private function byThreadId(array $threadIds): ?string
    {
        if ($threadIds === []) {
            return null;
        }

        /*
         * Both spellings. A client may echo the id with or without its angle
         * brackets, and matching only one form loses the correlation for half
         * of them.
         */
        $candidates = [];

        foreach ($threadIds as $id) {
            $bare = trim($id, '<> ');

            $candidates[] = $id;
            $candidates[] = $bare;
            $candidates[] = "<{$bare}>";
        }

        $ticketId = DB::table('ticket_messages')
            ->whereIn('provider_message_id', array_values(array_unique($candidates)))
            // The most recent, in case an id was somehow reused.
            ->orderByDesc('sent_at')
            ->value('ticket_id');

        return $ticketId === null ? null : (string) $ticketId;
    }

    private function byReference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $ticketId = DB::table('tickets')->where('reference', $reference)->value('id');

        return $ticketId === null ? null : (string) $ticketId;
    }

    /**
     * The one open ticket this identifier already has, if there is exactly one.
     *
     * "Exactly one" is the safety rail. With two candidates there is no
     * non-arbitrary answer, and picking the newest would be a coin toss
     * presented to the agent as a fact.
     */
    private function byOpenTicket(ChannelPayload $payload, int $windowHours): ?string
    {
        $customerId = DB::table('contact_identifiers')
            ->where('kind', $payload->sender->kind)
            ->where('value_normalised', $payload->sender->value)
            ->value('customer_id');

        if ($customerId === null) {
            return null;
        }

        $candidates = DB::table('tickets')
            ->where('customer_id', $customerId)
            ->where('channel', $payload->channel)
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::Pending->value])
            ->where('updated_at', '>=', now()->subHours($windowHours))
            ->orderByDesc('updated_at')
            ->limit(2)
            ->pluck('id')
            ->all();

        return count($candidates) === 1 ? (string) $candidates[0] : null;
    }
}
