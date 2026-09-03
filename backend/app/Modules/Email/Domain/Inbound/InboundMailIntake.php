<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain\Inbound;

use App\Modules\Email\Domain\SubjectTagger;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * One email in, one ticket or one reply out.
 *
 * The order of operations is the design:
 *
 *   1. CLAIM the external id. Before parsing, before anything. Providers retry
 *      — a webhook that times out after we committed will be delivered again,
 *      and a provider having a bad afternoon may deliver the same message five
 *      times. Claiming first means the duplicate loses an INSERT rather than
 *      being caught by a check-then-act race between two workers.
 *
 *   2. Parse. If it fails, quarantine the raw bytes. Never a best guess: a
 *      half-read email attached to a plausible ticket is worse than one an
 *      administrator can see was not handled.
 *
 *   3. Correlate, resolve the sender, and write through the ticket commands —
 *      never directly. The commands own the version guard, the history entry
 *      and the reopen rule, and an inbound path that wrote rows itself would
 *      have to reimplement all three and would get one of them wrong.
 */
final class InboundMailIntake
{
    public function __construct(
        private readonly MailParser $parser,
        private readonly TicketCorrelator $correlator,
        private readonly SenderResolver $senders,
        private readonly CreateTicket $createTicket,
        private readonly AppendMessage $appendMessage,
    ) {}

    /**
     * @return array{status: string, ticket_id?: string, reason?: string}
     */
    public function accept(string $raw, string $provider, ?string $externalId = null): array
    {
        $parsed = null;

        try {
            $parsed = $this->parser->parse($raw);
        } catch (Throwable $e) {
            // Claim the id anyway, so a provider retry of an unparseable
            // message does not fill quarantine with copies of it.
            $id = $externalId ?? $this->fingerprint($raw);

            if (! $this->claim($id, $provider, null, null)) {
                return ['status' => 'duplicate'];
            }

            $this->quarantine($id, $provider, $raw, $e->getMessage(), null);

            return ['status' => 'quarantined', 'reason' => $e->getMessage()];
        }

        /*
         * The provider's id where it gives one, else the message's own
         * Message-ID, else a hash of the bytes. The last is a genuine fallback:
         * a message with no Message-ID is malformed, but two identical
         * deliveries of it are still one email.
         */
        $id = $externalId ?? $parsed->messageId ?? $this->fingerprint($raw);

        if (! $this->claim($id, $provider, $parsed->fromAddress, $parsed->subject)) {
            return ['status' => 'duplicate'];
        }

        $correlation = $this->correlator->correlate($parsed);
        $sender = $this->senders->resolve($parsed);

        /*
         * `System`, with a reason. The customer wrote the words, but no PERSON
         * in this system performed the action — attributing it to an agent
         * would put a colleague's name against something they did not do, and
         * attributing it to the customer would imply a portal account they do
         * not have.
         */
        $actor = Actor::system('inbound_email');

        $ticketId = $correlation->isReply()
            ? (string) $correlation->ticketId
            : $this->openTicket($parsed, $sender['id'], $actor);

        $message = $this->appendMessage->handle(
            $actor,
            $ticketId,
            MessageDirection::Inbound,
            $parsed->body === '' ? '(no message body)' : $parsed->body,
        );

        DB::table('mail_inbound')->where('external_id', $id)->update([
            'status' => 'correlated',
            'ticket_id' => $ticketId,
            'message_id' => $message->getKey(),
            'customer_id' => $sender['id'],
            'correlation_trace' => json_encode([
                'winning_rule' => $correlation->winningRule,
                'attempts' => $correlation->trace,
                'sender_created' => $sender['created'],
                // So a loop guard decision is auditable after the fact.
                'automated' => $parsed->isAutomated,
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return ['status' => 'accepted', 'ticket_id' => $ticketId];
    }

    /**
     * Takes the external id, or discovers somebody already has.
     *
     * A unique index and a caught violation, rather than a SELECT then an
     * INSERT: two workers handed the same retry would both see "not present"
     * and both proceed.
     */
    private function claim(string $externalId, string $provider, ?string $from, ?string $subject): bool
    {
        try {
            DB::table('mail_inbound')->insert([
                'id' => (string) Str::ulid(),
                'external_id' => mb_substr($externalId, 0, 512),
                'provider' => $provider,
                'status' => 'received',
                'from_address' => $from,
                'subject' => $subject === null ? null : mb_substr($subject, 0, 512),
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            // Already ours. The duplicate delivery ends here, silently and
            // correctly.
            return false;
        }
    }

    private function openTicket(ParsedMail $mail, string $customerId, Actor $actor): string
    {
        $subject = trim($mail->subject);

        $ticket = $this->createTicket->handle($actor, new CreateTicketInput(
            // A subject is optional in email and required on a ticket; falling
            // back to a marker beats a blank row nobody can identify in a list.
            subject: $subject === '' ? '(no subject)' : mb_substr($subject, 0, 255),
            description: $mail->body === '' ? '(no message body)' : $mail->body,
            customerId: $customerId,
            channel: TicketChannel::Email,
            // Stops the loop before it starts: an out-of-office that opens a
            // ticket must not be acknowledged.
            suppressAcknowledgement: $mail->isAutomated,
        ));

        return (string) $ticket->getKey();
    }

    private function quarantine(string $externalId, string $provider, string $raw, string $reason, ?string $from): void
    {
        DB::table('mail_inbound')->where('external_id', $externalId)->update([
            'status' => 'quarantined',
            'updated_at' => now(),
        ]);

        DB::table('mail_quarantine')->insert([
            'id' => (string) Str::ulid(),
            'external_id' => mb_substr($externalId, 0, 512),
            'provider' => $provider,
            'from_address' => $from,
            'subject' => $this->bestEffortSubject($raw),
            'reason' => $reason,
            // In full. A parser bug is only diagnosable against the bytes that
            // broke it, and replaying once it is fixed is why this is kept.
            'raw' => $raw,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Whatever can be read without a parser, for a list an admin can scan. */
    private function bestEffortSubject(string $raw): ?string
    {
        return preg_match('/^Subject:\s*(.+)$/mi', $raw, $m) === 1
            ? mb_substr(trim($m[1]), 0, 512)
            : null;
    }

    /**
     * An id for a message that carries none.
     *
     * A hash of the bytes: two identical deliveries of a malformed message are
     * still one email, and without this each retry would become another ticket.
     */
    private function fingerprint(string $raw): string
    {
        return 'sha256:'.hash('sha256', $raw);
    }
}
