<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain;

/**
 * A ticket reference quoted inside a piece of text.
 *
 * Lives here rather than in Email because every channel needs it and only one
 * of them tags subjects. A customer who quotes `[#TKT-000042]` in a web form,
 * a WhatsApp message or an email means the same thing in all three, and a
 * second copy of this pattern in a second module is how two channels start
 * disagreeing about what a reference looks like.
 *
 * `SubjectTagger` in the Email module writes the tag and delegates its reading
 * to this class, so the pattern is declared exactly once.
 */
final class TicketReference
{
    /**
     * Matches a tag anywhere in the text, so a client that puts `Re:` first
     * does not defeat the check.
     */
    private const PATTERN = '/\[#([A-Z]{2,5}-[0-9]{4,})\]/';

    /** The ticket reference this text carries, if any. */
    public static function inText(string $text): ?string
    {
        return preg_match(self::PATTERN, $text, $matches) === 1 ? $matches[1] : null;
    }

    /** The same text with every reference tag removed. */
    public static function stripFrom(string $text): string
    {
        return trim((string) preg_replace(self::PATTERN, '', $text));
    }
}
