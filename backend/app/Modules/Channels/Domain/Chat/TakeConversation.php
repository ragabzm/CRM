<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Support\Facades\DB;

/**
 * An agent takes a waiting conversation.
 *
 * THE RACE IS RESOLVED IN THE DATABASE, not in the UI. Two agents watching the
 * same waiting list will click the same row within the same second — that is
 * not an edge case, it is what a shared list does — and a read-then-write
 * would let both of them "win", with the second silently overwriting the
 * first. One of them would then be typing into a conversation somebody else is
 * also answering.
 *
 * So the claim is a single conditional UPDATE with `WHERE taken_by IS NULL`.
 * Exactly one row is affected, and the loser is told, by name, who has it. A
 * refusal that said only "could not take" would send them back to the list to
 * try again on a row that is gone.
 */
final class TakeConversation
{
    public function handle(int $userId, string $conversationId): ChatConversation
    {
        $conversation = ChatConversation::query()->whereKey($conversationId)->first();

        if ($conversation === null) {
            throw ProblemException::make(
                'channels.chat_not_found',
                'Conversation not found',
                404,
                'That conversation does not exist.',
            );
        }

        if ($conversation->isFinished()) {
            throw ProblemException::make(
                'channels.chat_finished',
                'That conversation is over',
                409,
                'It has already ended. The transcript is on the ticket.',
                ['ticket_id' => $conversation->ticket_id, 'state' => $conversation->state()],
            );
        }

        $claimed = DB::table('chat_conversations')
            ->where('id', $conversationId)
            // The whole race, in one predicate.
            ->whereNull('taken_by')
            ->update(['taken_by' => $userId, 'taken_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            $conversation->refresh();

            if ((int) $conversation->taken_by === $userId) {
                // Already theirs — a double click, or a retried request. Not a
                // refusal: nothing is wrong and nothing changed.
                return $conversation;
            }

            throw ProblemException::make(
                'channels.chat_already_taken',
                'A colleague got there first',
                409,
                sprintf('%s is already answering this conversation.', $this->nameOf($conversation)),
                ['taken_by' => $conversation->taken_by],
            );
        }

        return $conversation->refresh();
    }

    private function nameOf(ChatConversation $conversation): string
    {
        $name = DB::table('users')->where('id', $conversation->taken_by)->value('name');

        // Never a bare id in prose somebody reads. "User 14 is answering this"
        // tells an agent nothing they can act on.
        return is_string($name) && $name !== '' ? $name : 'Another agent';
    }
}
