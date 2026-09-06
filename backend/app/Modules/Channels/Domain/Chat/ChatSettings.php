<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use App\Modules\Platform\Support\Settings\SettingsRegistry;

/**
 * The four numbers live chat runs on.
 *
 * Written out as constants rather than composed from the channel name, so
 * `SettingsHaveAReaderTest` can see that each one is actually read — a key
 * built by concatenation is a key no reader can grep for and no guard can
 * check.
 */
final class ChatSettings
{
    /**
     * How often the widget and the agent's pane ask for new messages.
     *
     * The SHORT interval, against AD-29's long one that every other screen
     * keeps. This is the number that decides whether chat feels like chat, and
     * it is also the number that decides the load — which is why it is a
     * setting rather than a constant somebody has to deploy to change.
     */
    public const POLL_SECONDS = 'channels.chat.poll_seconds';

    /** How long the visitor's token lives, and with it the conversation. */
    public const TOKEN_MINUTES = 'channels.chat.token_minutes';

    /** Silence, either way, after which the conversation is given up on. */
    public const ABANDON_AFTER_MINUTES = 'channels.chat.abandon_after_minutes';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function pollSeconds(): int
    {
        return (int) $this->settings->get(self::POLL_SECONDS);
    }

    public function tokenMinutes(): int
    {
        return (int) $this->settings->get(self::TOKEN_MINUTES);
    }

    public function abandonAfterMinutes(): int
    {
        return (int) $this->settings->get(self::ABANDON_AFTER_MINUTES);
    }
}
