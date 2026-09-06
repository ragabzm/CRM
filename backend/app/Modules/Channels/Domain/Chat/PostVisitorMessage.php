<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use App\Modules\Channels\Adapters\ChatChannelAdapter;
use App\Modules\Ai\Contracts\AiCapability;
use App\Modules\Channels\Domain\Intake\InboundIntake;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
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
        private readonly Chatbot $chatbot,
        private readonly SettingsRegistry $settings,
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

        /*
         * The chatbot takes its turn AFTER the visitor's message is on the
         * ticket, never instead of it.
         *
         * The order matters more than it looks. If the bot ran first and the
         * intake then failed, the customer would hold an answer to a question
         * nothing recorded. This way the worst case is a question sitting on a
         * ticket that nobody has answered — which is a support desk, and the
         * waiting list is exactly the mechanism for it.
         */
        $this->answerOrHandOff($conversation->refresh(), $body);

        return $result;
    }

    /**
     * Either the chatbot answers, or a person is fetched.
     *
     * There is no third outcome and no state where neither happens. With the
     * capability switched OFF, the first message hands off immediately — so
     * the waiting list never has to ask what the setting says, and an
     * administrator turning it off mid-conversation cannot strand somebody
     * halfway between a machine and a person.
     */
    private function answerOrHandOff(ChatConversation $conversation, string $body): void
    {
        if ($conversation->ticket_id === null || $conversation->handed_off_at !== null) {
            return;
        }

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        if (! (bool) $this->settings->get(AiCapability::Chatbot->setting())) {
            /*
             * Offered a person straight away, with NO mention of a
             * switched-off feature. A customer told that "the assistant is
             * unavailable" has been given a fact about our configuration and
             * nothing they can use.
             */
            $this->chatbot->handOff($conversation, (string) $conversation->ticket_id, $locale);

            return;
        }

        $this->chatbot->respondTo($conversation, $body, $locale);
    }
}
