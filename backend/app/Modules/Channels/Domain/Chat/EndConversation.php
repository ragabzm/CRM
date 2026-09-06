<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use Illuminate\Support\Facades\DB;

/**
 * Somebody closes the conversation.
 *
 * Idempotent, and deliberately quiet. Ending a conversation that has already
 * ended changes nothing and is not an error: the visitor closing the widget
 * and the agent closing the pane are two people doing the same thing, often
 * within seconds of each other, and refusing the second would surface an error
 * for something that went exactly right.
 *
 * The ticket is untouched. A finished chat is not a resolved request — the
 * agent may have promised to look into something — and closing the ticket here
 * would take that decision away from the person who has to make it.
 */
final class EndConversation
{
    public function handle(ChatConversation $conversation): ChatConversation
    {
        if ($conversation->isFinished()) {
            return $conversation;
        }

        DB::table('chat_conversations')
            ->where('id', $conversation->getKey())
            // The same `WHERE it has not happened yet` shape the claim uses:
            // two closes arriving together resolve to one write.
            ->whereNull('ended_at')
            ->whereNull('abandoned_at')
            ->update([
                'ended_at' => now(),
                // The token dies with the conversation. It was scoped to this
                // and nothing else, so there is nothing left for it to grant.
                'token_expires_at' => now(),
                'updated_at' => now(),
            ]);

        return $conversation->refresh();
    }
}
