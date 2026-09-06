<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * The visitor closed the tab.
 *
 * Most chats end this way — somebody gets their answer, or gives up waiting,
 * and simply leaves. Nobody presses a button, so without this the conversation
 * sits in the waiting list for ever and an agent keeps trying to answer
 * somebody who is not there.
 *
 * IDEMPOTENT, and the marking is what makes it so. `abandoned_at` is written
 * in the same transaction as the note, under a conditional update, so a second
 * run selects nothing and produces nothing new. A sweep that appended a note
 * and then marked the row would, on a crash between the two, leave a
 * conversation that gets a fresh "the visitor left" note every minute for ever.
 *
 * The ticket ALREADY EXISTS with the full transcript — it was created by the
 * first message and every message since is an ordinary `ticket_messages` row.
 * So there is nothing to assemble here: the note is the only new fact, and it
 * says the conversation ended without anybody closing it.
 *
 * A conversation with no ticket is one where the visitor opened the widget and
 * never typed. It is marked and left alone: there was no request, and creating
 * one would put silence in the queue.
 */
final class AbandonStaleConversations
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ChatSettings $settings,
        private readonly AppendMessage $appendMessage,
    ) {}

    /** @return int How many were given up on. */
    public function run(): int
    {
        $minutes = max(1, $this->settings->abandonAfterMinutes());
        $cutoff = now()->subMinutes($minutes);

        $stale = ChatConversation::query()
            ->whereNull('ended_at')
            ->whereNull('abandoned_at')
            ->where('last_activity_at', '<=', $cutoff)
            ->orderBy('last_activity_at')
            ->pluck('id');

        $count = 0;

        foreach ($stale as $id) {
            if ($this->abandon((string) $id)) {
                $count++;
            }
        }

        return $count;
    }

    private function abandon(string $id): bool
    {
        return $this->db->transaction(function () use ($id): bool {
            /*
             * The conditional update IS the claim. If another sweep marked it
             * between the list above and this line, zero rows change and this
             * run does nothing — which is the race the idempotence has to
             * survive, not merely tolerate.
             */
            $marked = DB::table('chat_conversations')
                ->where('id', $id)
                ->whereNull('ended_at')
                ->whereNull('abandoned_at')
                ->update([
                    'abandoned_at' => now(),
                    // The conversation is over, so the token is too.
                    'token_expires_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($marked === 0) {
                return false;
            }

            $ticketId = DB::table('chat_conversations')->where('id', $id)->value('ticket_id');

            if (! is_string($ticketId) || $ticketId === '') {
                // Opened and never typed in. No request was made.
                return true;
            }

            /*
             * An INTERNAL note, not a message to the customer.
             *
             * It is a fact about the channel for whoever picks the ticket up —
             * "they are not there any more, do not wait" — and sending it
             * outward would tell somebody who left that they left.
             */
            $this->appendMessage->handle(
                Actor::system('chat_abandoned'),
                $ticketId,
                MessageDirection::Internal,
                __('channels.chat.abandoned_note'),
            );

            return true;
        });
    }
}
