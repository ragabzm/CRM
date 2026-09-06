<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use App\Modules\Channels\Adapters\ChatChannelAdapter;
use App\Modules\Channels\Domain\ChannelAccountAvailability;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Support\Str;

/**
 * A stranger opens the widget.
 *
 * No ticket is created here. A box somebody opened and closed again is not a
 * request, and making one would fill the agents' queue with silence — the
 * ticket appears with the first thing they actually say.
 *
 * Two refusals happen before a conversation exists, which is the only point at
 * which refusing is free:
 *
 *   The CHANNEL may be switched off, like any other channel. Disabled, the
 *   widget is told so and says so; it does not render an empty box that
 *   swallows what somebody types.
 *
 *   The ORIGIN may not be on the allow-list. A widget script is something
 *   anybody can copy off a page's source, and an unlisted site pasting it in
 *   would get a working support channel staffed by our agents.
 */
final class StartConversation
{
    public function __construct(
        private readonly ChatSettings $settings,
        private readonly EmbedOrigins $origins,
        private readonly ChannelAccountAvailability $availability,
    ) {}

    /**
     * @return array{conversation: ChatConversation, token: string}
     */
    public function handle(?string $origin, ?string $visitorName, ?string $visitorIdentifier): array
    {
        if (! $this->availability->isOpen(ChatChannelAdapter::CHANNEL)) {
            throw ProblemException::make(
                'channels.chat_closed',
                'Chat is not available',
                503,
                'Live chat is switched off at the moment. Please use the contact form or email us.',
            );
        }

        if (! $this->origins->allows($origin)) {
            /*
             * No token, and no conversation row either. A refusal that still
             * created something would let an unlisted site fill the table by
             * reloading.
             */
            throw ProblemException::make(
                'channels.chat_origin_refused',
                'This site is not allowed to open a chat',
                403,
                'The widget is embedded on a site that has not been allowed. An administrator can add it.',
            );
        }

        $token = ChatToken::mint();
        $conversation = new ChatConversation;

        $conversation->forceFill([
            'ticket_id' => null,
            'channel_account_id' => ChatChannelAdapter::accountId(),
            'customer_id' => null,
            'visitor_name' => $this->clean($visitorName, 120),
            'visitor_identifier' => $this->clean($visitorIdentifier, 320),
            'origin' => $origin,
            'taken_by' => null,
            'last_activity_at' => now(),
            'token_hash' => ChatToken::hash($token),
            'token_expires_at' => now()->addMinutes(max(1, $this->settings->tokenMinutes())),
        ])->save();

        return ['conversation' => $conversation, 'token' => $token];
    }

    private function clean(?string $value, int $limit): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $limit);
    }
}
