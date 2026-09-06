<?php

declare(strict_types=1);

namespace App\Modules\Ai\Contracts;

/**
 * The five things AI may be asked to do, and there is no sixth.
 *
 * An enum rather than free strings, because each case is a SWITCH an
 * administrator can turn off independently — and a capability named by a
 * string somewhere would be one with no switch, which is the same as one that
 * cannot be turned off.
 *
 * The settings key is derived here so that adding a case adds its switch. A
 * capability that shipped without one would be AI running in a deployment that
 * believes it has AI disabled.
 */
enum AiCapability: string
{
    /** A short account of a long ticket, for an agent picking it up. */
    case Summary = 'summary';

    /** Draft wording an agent edits and sends. Never sent unattended. */
    case SuggestedReply = 'suggested_reply';

    /** A category the agent may accept. Nothing is applied automatically. */
    case CategoryProposal = 'category_proposal';

    /** Published articles that may answer this. */
    case SuggestedArticles = 'suggested_articles';

    /** The customer-facing chatbot, inside its own session. */
    case Chatbot = 'chatbot';

    /**
     * The switch that turns this capability off.
     *
     * Written out as literals rather than composed from `$this->value`. A key
     * built by concatenation is a key no reader can grep for and no guard can
     * check — `SettingsHaveAReaderTest` would report all five as offered in
     * the console and read by nothing, which would be true of the string it
     * was looking for and false of the setting.
     */
    public function setting(): string
    {
        return match ($this) {
            self::Summary => 'ai.capability.summary',
            self::SuggestedReply => 'ai.capability.suggested_reply',
            self::CategoryProposal => 'ai.capability.category_proposal',
            self::SuggestedArticles => 'ai.capability.suggested_articles',
            self::Chatbot => 'ai.capability.chatbot',
        };
    }

    /** @return list<string> */
    public static function settings(): array
    {
        return array_map(static fn (self $case): string => $case->setting(), self::cases());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
