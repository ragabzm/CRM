<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\TicketMessage;
use App\Modules\Tickets\Http\ActorResolver;
use App\Modules\Tickets\Http\Requests\AppendMessageRequest;
use Illuminate\Http\JsonResponse;

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
    public function index(string $ticket): JsonResponse
    {
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
        $data = $request->validated();

        $message = $this->append->handle(
            $this->actors->fromRequest($request),
            $ticket,
            // A staff reply is outbound unless it is explicitly logging
            // something the customer said by another route.
            MessageDirection::from((string) ($data['direction'] ?? MessageDirection::Outbound->value)),
            (string) $data['body'],
        );

        return new JsonResponse($this->shape($message), 201);
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
        ];
    }
}
