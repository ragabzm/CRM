<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

/**
 * Puts the ticket reference in the subject, once.
 *
 * The headers are how threading is SUPPOSED to work. The subject tag is what
 * happens when they do not: a client that strips unknown headers, a customer
 * forwarding from a webmail that rewrites the message, a reply typed into a
 * brand-new email because the old one scrolled away. In every one of those the
 * reference in the subject is the only thing left connecting the reply to its
 * ticket, and Story 5.2 reads it as its last resort.
 *
 * "Once" is the load-bearing word. A long thread would otherwise accumulate
 * `[#TKT-000042] Re: [#TKT-000042] Re: [#TKT-000042] …`, which is both absurd
 * and eventually longer than the subject line itself.
 */
final class SubjectTagger
{
    /**
     * Matches a tag anywhere in the line, so a client that puts `Re:` first
     * does not defeat the check.
     */
    private const TAG = '/\[#([A-Z]{2,5}-[0-9]{4,})\]/';

    public static function tag(string $subject, string $reference): string
    {
        $existing = self::referenceIn($subject);

        if ($existing === $reference) {
            // Already tagged with this ticket. Leave it exactly as the client
            // wrote it, `Re:` prefixes and all.
            return $subject;
        }

        if ($existing !== null) {
            /*
             * Tagged with a DIFFERENT ticket — a forward, or a reply that has
             * been dragged onto another thread. Replace rather than append: two
             * references in one subject would make Story 5.2 guess which
             * ticket the customer meant.
             */
            $subject = trim((string) preg_replace(self::TAG, '', $subject));
        }

        $subject = trim($subject);

        return $subject === ''
            ? "[#{$reference}]"
            : "[#{$reference}] {$subject}";
    }

    /** The ticket reference a subject carries, if any. */
    public static function referenceIn(string $subject): ?string
    {
        return preg_match(self::TAG, $subject, $matches) === 1 ? $matches[1] : null;
    }
}
