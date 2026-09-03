<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Portal;

use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Tickets\Contracts\CustomerRequestGateway;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Commands\ReopenTicket;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use Illuminate\Support\Facades\DB;

/**
 * What a customer is allowed to see and do with their own requests.
 *
 * Lives in Tickets, not Portal: every write goes through the same commands the
 * agent path uses, so the reference, the history entry, the version guard and
 * the reopen window all behave identically. A portal that wrote rows itself
 * would have to reimplement four rules and would get one of them wrong.
 *
 * THE SHAPE IS THE BOUNDARY. Nothing here serialises an internal note, an SLA
 * reading, an assignee, a priority or an agent's name. Those are not filtered
 * out at the edge — they are never put in, which is the difference between a
 * boundary and a habit.
 */
final class CustomerRequests implements CustomerRequestGateway
{
    /** Enough to scroll a year of requests on a phone. */
    private const LIMIT = 50;

    public function __construct(
        private readonly CreateTicket $create,
        private readonly AppendMessage $append,
        private readonly ReopenTicket $reopen,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $customerId): array
    {
        return Ticket::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Ticket $ticket): array => $this->summary($ticket))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(string $customerId, string $ticketId): ?array
    {
        $ticket = $this->find($customerId, $ticketId);

        if ($ticket === null) {
            return null;
        }

        return [
            ...$this->summary($ticket),
            'description' => $ticket->description,
            'messages' => $this->thread($ticketId),
        ];
    }

    /**
     * @param  list<string>  $attachmentIds
     * @return array<string, mixed>
     */
    public function submit(
        string $customerId,
        string $accountId,
        string $accountName,
        string $subject,
        string $description,
        ?int $categoryId,
        array $attachmentIds,
    ): array {
        $ticket = $this->create->handle(
            Actor::portal($accountId, $accountName),
            new CreateTicketInput(
                subject: $subject,
                description: $description,
                // From the ACCOUNT, never the body: a caller-supplied customer
                // id would let any portal user open a request against anybody
                // else's record.
                customerId: $customerId,
                // Fixed, not chosen. A submission that could claim
                // `channel: agent` would misreport where the work came from.
                channel: TicketChannel::Portal,
                categoryId: $categoryId,
            ),
        );

        $this->claimAttachments($customerId, (string) $ticket->getKey(), $attachmentIds);

        return $this->summary($ticket);
    }

    /**
     * @param  list<string>  $attachmentIds
     * @return array<string, mixed>|null
     */
    public function reply(
        string $customerId,
        string $accountId,
        string $accountName,
        string $ticketId,
        string $body,
        array $attachmentIds,
    ): ?array {
        if ($this->find($customerId, $ticketId) === null) {
            return null;
        }

        /*
         * Inbound, through the same command an email reply uses — which is what
         * flips a `pending` ticket back to open and notifies the assignee. None
         * of that is mentioned in what comes back: a customer does not need to
         * be told the internal state machine moved, and telling them invites a
         * question about a word that means nothing to them.
         */
        $this->append->handle(
            Actor::portal($accountId, $accountName),
            $ticketId,
            MessageDirection::Inbound,
            $body,
            $attachmentIds,
        );

        return $this->show($customerId, $ticketId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reopen(string $customerId, string $accountId, string $accountName, string $ticketId): ?array
    {
        $ticket = $this->find($customerId, $ticketId);

        if ($ticket === null) {
            return null;
        }

        /*
         * The window check lives in TicketLifecycle and throws from there. It
         * is not repeated here: a second copy of "how long is too long" is a
         * second thing to get wrong, and the one that is wrong would be the one
         * customers actually hit.
         *
         * No version submitted — a customer is not editing a form and has no
         * version to be stale against.
         */
        $this->reopen->handle(Actor::portal($accountId, $accountName), $ticketId, null);

        return $this->show($customerId, $ticketId);
    }

    private function find(string $customerId, string $ticketId): ?Ticket
    {
        /*
         * Both conditions, always. Looking up by id and then checking the
         * owner would be the same query with a window in which somebody could
         * be handed a ticket that is not theirs.
         */
        return Ticket::query()
            ->whereKey($ticketId)
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * What a customer is shown about their own request.
     *
     * Deliberately short. No assignee — naming the agent invites a customer to
     * ask for them by name and turns a desk into a set of personal queues. No
     * priority — it is the business's own triage, and a customer reading "low"
     * hears "we do not care". No SLA — a countdown a customer can watch is a
     * promise nobody made to them.
     *
     * @return array<string, mixed>
     */
    private function summary(Ticket $ticket): array
    {
        return [
            'id' => (string) $ticket->getKey(),
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'created_at' => $ticket->created_at?->toIso8601ZuluString(),
            'updated_at' => $ticket->updated_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * The thread, as the customer saw it.
     *
     * `customerVisible()` is the whole safeguard: an internal note is a
     * colleague's private remark ABOUT the person who would be reading it, and
     * this is one of the two places that could leak one.
     *
     * @return list<array<string, mixed>>
     */
    private function thread(string $ticketId): array
    {
        return TicketMessage::query()
            ->where('ticket_id', $ticketId)
            ->customerVisible()
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TicketMessage $message): array => [
                'id' => (string) $message->getKey(),
                /*
                 * `you` or `support`, not the agent's name. The customer needs
                 * to know which side spoke; who exactly is the desk's business,
                 * and a name here follows them into every later conversation.
                 */
                'from' => $message->direction === MessageDirection::Inbound ? 'you' : 'support',
                'body' => $message->body,
                'sent_at' => $message->sent_at?->toIso8601ZuluString(),
                'attachments' => $this->attachmentsOf((string) $message->getKey()),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachmentsOf(string $messageId): array
    {
        return DB::table('attachments')
            ->where('owner_type', AttachmentOwnerType::Message->value)
            ->where('owner_id', $messageId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $a): array => [
                'id' => (string) $a->id,
                'filename' => $a->filename,
                'byte_size' => (int) $a->byte_size,
            ])
            ->all();
    }

    /**
     * Moves files uploaded against the customer onto the new request.
     *
     * @param  list<string>  $attachmentIds
     */
    private function claimAttachments(string $customerId, string $ticketId, array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        DB::table('attachments')
            ->whereIn('id', $attachmentIds)
            // Only files this customer uploaded. Otherwise naming any id would
            // pull a stranger's file into their own request.
            ->where('owner_type', AttachmentOwnerType::Customer->value)
            ->where('owner_id', $customerId)
            ->update([
                'owner_type' => AttachmentOwnerType::Ticket->value,
                'owner_id' => $ticketId,
            ]);
    }
}
