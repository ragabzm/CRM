<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain\Inbound;

use App\Modules\Email\Domain\SubjectTagger;
use Illuminate\Support\Facades\DB;

/**
 * Which ticket this email belongs to, and how we decided.
 *
 * Four rules, in descending order of how much the sender's mail client had to
 * get right:
 *
 *   1. `In-Reply-To` — the client threaded properly. Almost always correct.
 *   2. `References`  — the client kept the chain but not the parent, which
 *                      happens when a middle message was deleted.
 *   3. Subject tag   — the headers were stripped, by a webmail rewriting the
 *                      message or by a customer replying from a fresh email.
 *   4. Body token    — the subject was retyped too. Last resort.
 *
 * Then: a new ticket. Guessing from the sender's address alone is deliberately
 * NOT a rule — a customer with three open tickets would have their reply
 * attached to whichever one happened to be newest, and the mistake would be
 * invisible to everyone including them.
 *
 * Every attempt is recorded, not just the winner. "Why did this land on that
 * ticket?" is the question a mis-correlated email raises, and without the trace
 * the only way to answer is to re-run the logic against a message that has
 * since been consumed.
 */
final class TicketCorrelator
{
    public function correlate(ParsedMail $mail): CorrelationResult
    {
        $trace = [];

        foreach ([
            'in_reply_to' => fn (): ?string => $this->byMessageId(
                $mail->inReplyTo === null ? [] : [$mail->inReplyTo]
            ),
            'references' => fn (): ?string => $this->byMessageId($mail->references),
            'subject_token' => fn (): ?string => $this->byReference(SubjectTagger::referenceIn($mail->subject)),
            'body_token' => fn (): ?string => $this->byReference($this->referenceInBody($mail->body)),
        ] as $rule => $attempt) {
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
     * Finds the ticket that sent one of these message ids.
     *
     * Reads `email_message_id`, which Story 5.1 stores at the moment a reply
     * goes out — the value the customer's client actually saw, rather than one
     * derived again later and possibly differently.
     *
     * @param  list<string>  $messageIds
     */
    private function byMessageId(array $messageIds): ?string
    {
        if ($messageIds === []) {
            return null;
        }

        /*
         * Both spellings. A client may echo the id with or without its angle
         * brackets, and matching only one form loses the correlation for half
         * of them.
         */
        $candidates = [];

        foreach ($messageIds as $id) {
            $bare = trim($id, '<> ');

            $candidates[] = $id;
            $candidates[] = $bare;
            $candidates[] = "<{$bare}>";
        }

        $ticketId = DB::table('ticket_messages')
            ->whereIn('email_message_id', array_values(array_unique($candidates)))
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
     * A ticket reference quoted anywhere in the text.
     *
     * The last resort, and deliberately the same pattern the subject uses: the
     * reference the customer is quoting is almost always one we put in front of
     * them, in a subject line or the acknowledgement body.
     */
    private function referenceInBody(string $body): ?string
    {
        return SubjectTagger::referenceIn($body);
    }
}
