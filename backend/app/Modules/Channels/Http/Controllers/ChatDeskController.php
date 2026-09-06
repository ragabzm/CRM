<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Channels\Domain\Chat\ChatConversation;
use App\Modules\Channels\Domain\Chat\ChatSettings;
use App\Modules\Channels\Domain\Chat\EndConversation;
use App\Modules\Channels\Domain\Chat\TakeConversation;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The agent's side: who is waiting, and taking one of them.
 *
 * A LIST, not a queue. No priority, no routing, no assignment strategy, no
 * position number and nothing that hands a conversation to somebody. Agents
 * look at who is waiting and one of them clicks. Every mechanism that would
 * make that decision for them is a mechanism that needs an availability state
 * to work, and availability is exactly what this story rules out.
 *
 * Replies are NOT sent from here. An agent answers a chat the same way they
 * answer anything else — through the ticket's own message endpoint — because
 * the conversation is a ticket and its transcript is ordinary messages. A
 * second send path would be a second place for the delivery state, the
 * attachment rules and the internal-note guard to be got wrong.
 */
final class ChatDeskController extends Controller
{
    public function __construct(
        private readonly TakeConversation $take,
        private readonly EndConversation $end,
        private readonly ChatSettings $settings,
    ) {}

    /**
     * Who is waiting, oldest first, plus what this agent already holds.
     *
     * Both in one response: an agent's chat pane needs "is anybody waiting?"
     * and "what am I in the middle of?" on the same interval, and asking twice
     * would let the two disagree.
     */
    public function index(Request $request): JsonResponse
    {
        $me = (int) ($request->user()?->getAuthIdentifier() ?? 0);

        $waiting = ChatConversation::query()
            ->whereNull('taken_by')
            ->whereNull('ended_at')
            ->whereNull('abandoned_at')
            // Only conversations somebody has actually spoken in. A widget
            // opened and left alone is not a person waiting for an answer.
            ->whereNotNull('ticket_id')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $mine = ChatConversation::query()
            ->where('taken_by', $me)
            ->whereNull('ended_at')
            ->whereNull('abandoned_at')
            ->orderByDesc('last_activity_at')
            ->limit(50)
            ->get();

        return new JsonResponse([
            'data' => [
                'poll_seconds' => $this->settings->pollSeconds(),
                'waiting' => $this->shape($waiting->all()),
                'mine' => $this->shape($mine->all()),
            ],
        ]);
    }

    public function take(Request $request, string $conversation): JsonResponse
    {
        $taken = $this->take->handle(
            (int) ($request->user()?->getAuthIdentifier() ?? 0),
            $conversation,
        );

        return new JsonResponse(['data' => $this->shape([$taken])[0]]);
    }

    public function close(Request $request, string $conversation): JsonResponse
    {
        $row = ChatConversation::query()->whereKey($conversation)->first();

        if ($row === null) {
            throw ProblemException::make(
                'channels.chat_not_found',
                'Conversation not found',
                404,
                'That conversation does not exist.',
            );
        }

        return new JsonResponse(['data' => $this->shape([$this->end->handle($row)])[0]]);
    }

    /**
     * @param  list<ChatConversation>  $conversations
     * @return list<array<string, mixed>>
     */
    private function shape(array $conversations): array
    {
        $ticketIds = array_values(array_filter(array_map(
            static fn (ChatConversation $c): ?string => $c->ticket_id === null ? null : (string) $c->ticket_id,
            $conversations,
        )));

        // One lookup for the page, not one per row.
        $tickets = $ticketIds === []
            ? collect()
            : DB::table('tickets')->whereIn('id', $ticketIds)->get(['id', 'reference', 'subject'])->keyBy('id');

        return array_map(static function (ChatConversation $c) use ($tickets): array {
            $ticket = $c->ticket_id === null ? null : $tickets->get((string) $c->ticket_id);

            return [
                'id' => (string) $c->getKey(),
                'state' => $c->state(),
                'visitor_name' => $c->visitor_name,
                'ticket_id' => $c->ticket_id === null ? null : (string) $c->ticket_id,
                'reference' => $ticket === null ? null : (string) $ticket->reference,
                'subject' => $ticket === null ? null : (string) $ticket->subject,
                'taken_by' => $c->taken_by === null ? null : (int) $c->taken_by,
                'waiting_since' => $c->created_at?->toIso8601ZuluString(),
                'last_activity_at' => $c->last_activity_at?->toIso8601ZuluString(),
            ];
        }, $conversations);
    }
}
