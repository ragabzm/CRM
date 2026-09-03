<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\DeliveryState;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use App\Modules\Tickets\Http\ActorResolver;
use App\Modules\Tickets\Http\Requests\AppendMessageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The ticket thread.
 *
 * Append-only and NOT version-guarded: replying is not a change to the ticket's
 * contended state, and two colleagues replying at once have not conflicted.
 */
final class TicketMessagesController extends Controller
{
    public function __construct(
        private readonly ActorResolver $actors,
        private readonly AppendMessage $append,
    ) {}

    /**
     * @response array{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request, string $ticket): JsonResponse
    {
        /*
         * The row-level rule, which this endpoint was missing.
         *
         * `can.capability:ticket.read` on the route says an agent may read
         * tickets — not that they may read THIS one. Without the check below,
         * any agent holding that capability could fetch the entire
         * conversation on a ticket assigned to a colleague by naming its id,
         * while `GET /tickets/{id}` correctly answered 404 for the same ticket.
         * The message bodies are the most sensitive thing a ticket holds.
         */
        $this->assertVisible($request, $ticket);

        $messages = TicketMessage::query()
            ->where('ticket_id', $ticket)
            // Oldest first: a thread is read top to bottom.
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get();

        return new JsonResponse([
            'data' => $messages->map(fn (TicketMessage $m) => $this->shape($m))->all(),
        ]);
    }

    /**
     * @response array<string, mixed>
     */
    public function store(AppendMessageRequest $request, string $ticket): JsonResponse
    {
        // Writing into a ticket you cannot see is the same leak as reading it,
        // one step further: it puts your words in someone else's conversation.
        $this->assertVisible($request, $ticket);

        $data = $request->validated();

        $message = $this->append->handle(
            $this->actors->fromRequest($request),
            $ticket,
            // A staff reply is outbound unless it is explicitly logging
            // something the customer said by another route, or is an internal
            // note meant for colleagues only.
            MessageDirection::from((string) ($data['direction'] ?? MessageDirection::Outbound->value)),
            (string) $data['body'],
            $request->attachmentIds(),
        );

        return new JsonResponse($this->shape($message), 201);
    }

    /**
     * 404 for a ticket the caller may not see — never 403.
     *
     * A 403 would confirm the ticket exists, which is itself information: an
     * agent could walk ids and learn how many tickets the business has.
     */
    private function assertVisible(Request $request, string $ticket): void
    {
        $actor = $request->user();

        $query = Ticket::query()->whereKey($ticket);

        if ($actor !== null) {
            TicketVisibility::scopeForActor($query, $actor);
        }

        if (! $query->exists()) {
            throw ProblemException::make(
                'tickets.not_found',
                'Ticket not found',
                404,
                'No ticket matches this identifier.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(TicketMessage $message): array
    {
        return [
            'id' => (string) $message->getKey(),
            'ticket_id' => (string) $message->ticket_id,
            'direction' => $message->direction->value,
            'author' => [
                'type' => $message->author_type,
                'id' => $message->author_id,
                'name' => $message->author_name,
            ],
            'body' => $message->body,
            'sent_at' => $message->sent_at?->toIso8601String(),

            /*
             * Null for anything that was never sent anywhere. The screen shows
             * a delivery chip only where there is a delivery to report, rather
             * than labelling an arriving customer message "queued".
             */
            'delivery_state' => $message->delivery_state?->value,

            'attachments' => $this->attachmentsOf($message),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachmentsOf(TicketMessage $message): array
    {
        /*
         * Through the query builder, not Platform's Attachment model.
         * Reading another module's TABLE is narrow and greppable; reading its
         * model couples this controller to an aggregate's behaviour and breaks
         * it whenever that aggregate changes.
         */
        return DB::table('attachments')
            ->where('owner_type', AttachmentOwnerType::Message->value)
            ->where('owner_id', $message->getKey())
            ->orderBy('id')
            ->get()
            ->map(static fn (object $a): array => [
                'id' => (string) $a->id,
                'filename' => $a->filename,
                'byte_size' => (int) $a->byte_size,
                // Server-sniffed, never the client's claim.
                'mime_type' => $a->mime_type,
                'scan_status' => $a->scan_status,
            ])
            ->all();
    }

    /**
     * Puts a failed send back in the queue.
     *
     * Retry rather than "send again": the message already exists and already
     * says who wrote it and when. Creating a second one would put the agent's
     * words in the thread twice for a failure that was never theirs.
     *
     * @response array<string, mixed>
     */
    public function retry(Request $request, string $ticket, string $message): JsonResponse
    {
        $this->assertVisible($request, $ticket);

        $found = TicketMessage::query()
            ->where('ticket_id', $ticket)
            ->whereKey($message)
            ->first();

        if ($found === null) {
            throw ProblemException::make(
                'tickets.message_not_found',
                'Message not found',
                404,
                'No message with that id on this ticket.',
            );
        }

        if ($found->delivery_state !== DeliveryState::Failed) {
            /*
             * Only a failure can be retried. Re-queueing something already on
             * its way would send it twice, and re-queueing an inbound message
             * is meaningless — it has nowhere to go.
             */
            throw ProblemException::make(
                'tickets.message_not_retryable',
                'This message cannot be retried',
                422,
                'Only a message that failed to send can be retried.',
            );
        }

        $found->forceFill(['delivery_state' => DeliveryState::Queued->value])->save();

        return new JsonResponse($this->shape($found->refresh()));
    }
}
