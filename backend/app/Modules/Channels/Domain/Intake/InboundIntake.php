<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Intake;

use App\Modules\Channels\Contracts\ChannelAdapter;
use App\Modules\Channels\Contracts\ChannelPayload;
use App\Modules\Channels\Exceptions\UnparseableChannelPayload;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
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
 * One message in, one ticket or one reply out — for every channel there is.
 *
 * The order of operations is the design, and it is the same order for email, a
 * web form, and every transport that follows:
 *
 *   1. CLAIM the provider's id. Before parsing, before anything. Providers
 *      retry — a webhook that times out after we committed will be delivered
 *      again, and a provider having a bad afternoon may deliver the same
 *      message five times. Claiming first means the duplicate loses an INSERT
 *      rather than being caught by a check-then-act race between two workers.
 *
 *   2. Parse. If it fails, quarantine the raw payload. Never a best guess: a
 *      half-read message attached to a plausible ticket is worse than one an
 *      administrator can see was not handled.
 *
 *   3. Correlate, resolve the sender, resolve the department, and write through
 *      the ticket commands — never directly. The commands own the version
 *      guard, the history entry and the reopen rule, and an inbound path that
 *      wrote rows itself would have to reimplement all three and would get one
 *      of them wrong.
 *
 * There is exactly one of these. An adapter supplies a `ChannelPayload` and
 * nothing else; it does not get to bring its own idempotency, its own
 * correlation or its own department rule. That is the entire reason this class
 * exists — five transports with five pipelines is five different products.
 */
final class InboundIntake
{
    public function __construct(
        private readonly TicketCorrelator $correlator,
        private readonly CustomerResolver $customers,
        private readonly DepartmentResolver $departments,
        private readonly CreateTicket $createTicket,
        private readonly AppendMessage $appendMessage,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * Parses and accepts one raw payload.
     *
     * @param  array<string, mixed>  $raw
     * @return array{status: string, ticket_id?: string, reason?: string}
     */
    public function accept(ChannelAdapter $adapter, array $raw, ?string $providerMessageId = null): array
    {
        $channel = $adapter->channel();

        try {
            $payload = $adapter->parse($raw);
        } catch (UnparseableChannelPayload|Throwable $e) {
            /*
             * Claim the id anyway, so a provider retry of an unparseable
             * message does not fill quarantine with copies of it.
             */
            $id = $providerMessageId ?? $adapter->fingerprint($raw);

            if (! $this->claim($channel, $id, null, null, $raw)) {
                return ['status' => 'duplicate'];
            }

            $this->quarantine(
                $channel,
                $id,
                $adapter->rawText($raw),
                $e->getMessage(),
                $adapter->bestEffortSubject($raw),
                // Whose format broke the parser, when the transport knows.
                is_string($raw['provider'] ?? null) ? $raw['provider'] : $channel,
            );

            return ['status' => 'quarantined', 'reason' => $e->getMessage()];
        }

        return $this->handle($payload, $raw);
    }

    /**
     * Accepts an already-parsed payload.
     *
     * Separate from `accept` because a form has nothing to parse: its fields
     * arrived validated, and running them through a parser that cannot fail
     * would be theatre. Everything after parsing is identical.
     *
     * @param  array<string, mixed>  $raw
     * @return array{status: string, ticket_id?: string, reason?: string}
     */
    public function handle(ChannelPayload $payload, array $raw = []): array
    {
        if (! $this->claim(
            $payload->channel,
            $payload->providerMessageId,
            $payload->sender->value,
            $payload->subject,
            $raw === [] ? $payload->rawPayload : $raw,
            $payload,
        )) {
            /*
             * Already ours. The duplicate delivery ends here, and it returns
             * the ticket the FIRST delivery produced rather than an error — a
             * provider retrying a message it already delivered has done
             * nothing wrong, and telling it otherwise makes it retry again.
             */
            return [
                'status' => 'duplicate',
                'ticket_id' => (string) DB::table('inbound_messages')
                    ->where('channel', $payload->channel)
                    ->where('provider_message_id', $payload->providerMessageId)
                    ->value('ticket_id'),
            ];
        }

        $correlation = $this->correlator->correlate($payload, $this->windowFor($payload->channel));
        $sender = $this->customers->resolve($payload->channel, $payload->sender);

        /*
         * `System`, with a reason naming the channel. The customer wrote the
         * words, but no PERSON in this system performed the action —
         * attributing it to an agent would put a colleague's name against
         * something they did not do, and attributing it to the customer would
         * imply a portal account they do not have.
         */
        $actor = Actor::system('inbound_'.$payload->channel);

        $departmentRule = null;

        if ($correlation->isReply()) {
            $ticketId = (string) $correlation->ticketId;
        } else {
            $department = $this->departments->resolve(null, $payload->channelAccountId, $sender['id']);
            $departmentRule = $department['rule'];
            $ticketId = $this->openTicket($payload, $sender['id'], $department['department_id'], $actor);
        }

        $message = $this->appendMessage->handle(
            $actor,
            $ticketId,
            MessageDirection::Inbound,
            $payload->body === '' ? '(no message body)' : $payload->body,
        );

        $this->reownAttachments($payload, $ticketId, (string) $message->getKey());

        DB::table('inbound_messages')
            ->where('channel', $payload->channel)
            ->where('provider_message_id', $payload->providerMessageId)
            ->update([
                'delivery_state' => 'correlated',
                'ticket_id' => $ticketId,
                'message_id' => $message->getKey(),
                'customer_id' => $sender['id'],
                'correlation_reason' => $correlation->winningRule,
                'department_rule' => $departmentRule,
                'correlation_trace' => json_encode([
                    'winning_rule' => $correlation->winningRule,
                    'attempts' => $correlation->trace,
                    'sender_created' => $sender['created'],
                    // So a loop guard decision is auditable after the fact.
                    'automated' => $payload->isAutomated,
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        return ['status' => 'accepted', 'ticket_id' => $ticketId];
    }

    /**
     * How far back the open-ticket correlation rule looks, per channel.
     *
     * Written out rather than composed from the channel name. A key built by
     * concatenation is a key no reader can grep for and no guard can check —
     * `SettingsHaveAReaderTest` would report both of these as registered and
     * read by nothing, which would be true of the string it was looking for
     * and false of the setting.
     *
     * A channel absent from this map has no window, which means the rule is off
     * for it. That is the right default for a transport nobody has thought
     * about yet.
     *
     * @var array<string, string>
     */
    private const CORRELATION_WINDOW_SETTINGS = [
        'email' => 'channels.correlation_window_hours.email',
        'web_form' => 'channels.correlation_window_hours.web_form',
    ];

    /** Zero means the rule is off. Email defaults to zero: see `TicketCorrelator`. */
    private function windowFor(string $channel): int
    {
        $key = self::CORRELATION_WINDOW_SETTINGS[$channel] ?? null;

        return $key === null ? 0 : (int) $this->settings->get($key);
    }

    /**
     * Takes the provider's id, or discovers somebody already has.
     *
     * A unique index and a caught violation, rather than a SELECT then an
     * INSERT: two workers handed the same retry would both see "not present"
     * and both proceed.
     *
     * @param  array<string, mixed>  $raw
     */
    private function claim(
        string $channel,
        string $providerMessageId,
        ?string $sender,
        ?string $subject,
        array $raw,
        ?ChannelPayload $payload = null,
    ): bool {
        try {
            DB::table('inbound_messages')->insert([
                'id' => (string) Str::ulid(),
                'channel' => $channel,
                'provider_message_id' => mb_substr($providerMessageId, 0, 512),
                'channel_account_id' => $payload?->channelAccountId,
                'direction' => 'inbound',
                'delivery_state' => 'received',
                'sender_identifier' => $sender === null ? '' : mb_substr($sender, 0, 320),
                'recipient_identifier' => $payload?->recipientIdentifier,
                'subject' => $subject === null ? null : mb_substr($subject, 0, 512),
                'body' => $payload?->body,
                'headers' => $payload === null ? null : json_encode($payload->headers, JSON_THROW_ON_ERROR),
                'raw_payload' => json_encode($raw, JSON_THROW_ON_ERROR),
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    private function openTicket(
        ChannelPayload $payload,
        string $customerId,
        ?int $departmentId,
        Actor $actor,
    ): string {
        $subject = trim($payload->subject);

        $ticket = $this->createTicket->handle($actor, new CreateTicketInput(
            // A subject is optional on some channels and required on a ticket;
            // falling back to a marker beats a blank row nobody can identify
            // in a list.
            subject: $subject === '' ? '(no subject)' : mb_substr($subject, 0, 255),
            description: $payload->body === '' ? '(no message body)' : $payload->body,
            customerId: $customerId,
            channel: TicketChannel::tryFrom($payload->channel) ?? TicketChannel::System,
            // Null for a transport that does not ask. Nothing is inferred: a
            // guessed category looks like somebody decided.
            categoryId: $payload->categoryId,
            departmentId: $departmentId,
            // Stops the loop before it starts: an out-of-office that opens a
            // ticket must not be acknowledged.
            suppressAcknowledgement: $payload->isAutomated,
        ));

        return (string) $ticket->getKey();
    }

    /**
     * Hands attachments uploaded before the ticket existed to the ticket.
     *
     * The web form's uploader has no ticket to attach to — the ticket is
     * created by this very call — so the files are parked against a draft
     * token and adopted here. Anything not owned by the draft this payload
     * names is left alone: an id supplied by a caller is not evidence they own
     * the file.
     */
    private function reownAttachments(ChannelPayload $payload, string $ticketId, string $messageId): void
    {
        if ($payload->attachmentIds === []) {
            return;
        }

        DB::table('attachments')
            ->whereIn('id', $payload->attachmentIds)
            ->where('owner_type', 'web_form_draft')
            ->update([
                'owner_type' => 'message',
                'owner_id' => $messageId,
                'updated_at' => now(),
            ]);
    }

    private function quarantine(
        string $channel,
        string $id,
        string $raw,
        string $reason,
        ?string $subject,
        string $provider,
    ): void
    {
        DB::table('inbound_messages')
            ->where('channel', $channel)
            ->where('provider_message_id', $id)
            ->update(['delivery_state' => 'quarantined', 'updated_at' => now()]);

        DB::table('channel_quarantine')->insert([
            'id' => (string) Str::ulid(),
            'channel' => $channel,
            'external_id' => mb_substr($id, 0, 512),
            'provider' => $provider,
            'from_address' => null,
            'subject' => $subject === null ? null : mb_substr($subject, 0, 512),
            'reason' => $reason,
            // In full. A parser bug is only diagnosable against the bytes that
            // broke it, and replaying once it is fixed is why this is kept.
            'raw' => $raw,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
