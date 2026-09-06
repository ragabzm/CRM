<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use App\Modules\Channels\Adapters\ChatChannelAdapter;
use App\Modules\Channels\Domain\Intake\InboundIntake;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Support\Facades\DB;

/**
 * The visitor says something.
 *
 * Straight into Story 7.1's pipeline — the same claim, the same correlation,
 * the same department ladder, the same commands — with ONE difference from
 * every other channel: chat already knows which ticket this is. The
 * conversation holds it, so the correlator is told rather than asked, and a
 * visitor who happens to type a ticket reference into the box cannot move
 * their own conversation onto somebody else's ticket.
 *
 * The first message is what creates the ticket. Everything after appends to it.
 */
final class PostVisitorMessage
{
    public function __construct(
        private readonly InboundIntake $intake,
        private readonly ChatChannelAdapter $adapter,
    ) {}

    /**
     * @return array{status: string, ticket_id?: string, reason?: string}
     */
    public function handle(ChatConversation $conversation, string $body): array
    {
        if (! $conversation->acceptsVisitor()) {
            throw ProblemException::make(
                'channels.chat_finished',
                'This conversation is over',
                409,
                'Start a new one to carry on.',
                ['state' => $conversation->state()],
            );
        }

        $result = $this->intake->accept(
            $this->adapter,
            [
                'conversation_id' => (string) $conversation->getKey(),
                'body' => $body,
                'visitor_name' => $conversation->visitor_name,
                'visitor_identifier' => $conversation->visitor_identifier,
            ],
            null,
            // Known, not inferred. See the class note.
            $conversation->ticket_id === null ? null : (string) $conversation->ticket_id,
        );

        $ticketId = $result['ticket_id'] ?? null;

        /*
         * Written back on the FIRST message, and never again.
         *
         * `last_activity_at` moves on every message either way — the
         * abandonment sweep reads it, and "inactive" has to mean nobody has
         * said anything rather than the visitor has stopped typing.
         */
        $changes = ['last_activity_at' => now(), 'updated_at' => now()];

        if ($conversation->ticket_id === null && is_string($ticketId) && $ticketId !== '') {
            $changes['ticket_id'] = $ticketId;

            /*
             * Read from `inbound_messages`, which this module owns and which
             * the intake just wrote — not from `tickets`.
             *
             * The value is the same either way; the difference is that only
             * Tickets touches its own tables, and a guard that reads them from
             * here is one refactor away from a guard that writes them.
             */
            $changes['customer_id'] = DB::table('inbound_messages')
                ->where('channel', ChatChannelAdapter::CHANNEL)
                ->where('ticket_id', $ticketId)
                ->orderByDesc('id')
                ->value('customer_id');
        }

        DB::table('chat_conversations')->where('id', $conversation->getKey())->update($changes);

        return $result;
    }
}
