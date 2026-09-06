<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Sanitiser;

/**
 * Takes personal detail out of text before it leaves the building.
 *
 * DETERMINISTIC, entirely. No model, no scoring, no threshold: the same input
 * produces the same output on every run, which is the only way a redactor can
 * be tested at all — and a redactor nobody can test is a redactor nobody
 * should trust with a customer's address.
 *
 * It works in two layers, and the difference between them is the honest part.
 *
 * DECLARED values are the reliable layer. The caller knows the customer's
 * name, their address and the agent's name, because it just read them off the
 * record; it passes them in, and they are removed exactly. Nothing is being
 * inferred.
 *
 * PATTERNS are the net underneath: email addresses, phone numbers, ticket and
 * customer references, and postal addresses in either language. These catch
 * what the caller did not know was in the body — the second phone number a
 * customer typed into a sentence.
 *
 * What is left over is the hard case, and it is where a redactor tuned on
 * Latin script leaks on the first Arabic ticket. A name in running Arabic
 * prose has no capitalisation to key on. So anything the two layers leave
 * behind that still LOOKS like a person is not cleaned up and sent anyway —
 * the line carrying it is omitted, and the omission is marked so the model is
 * not silently reading a sentence with a hole in it.
 */
final class Redactor
{
    public const REDACTED = '[redacted]';

    public const OMITTED = '[omitted]';

    /**
     * Arabic name markers.
     *
     * NOT a list of names — that could never be complete, and a list that is
     * nearly complete is worse than none because it reads as coverage. These
     * are the words that PRECEDE a name in Arabic writing: honorifics, titles,
     * and the two vocatives somebody uses when addressing a person by name.
     * A token following one of them is treated as a name we cannot confirm,
     * and its line is omitted rather than sent.
     *
     * @var list<string>
     */
    private const ARABIC_NAME_MARKERS = [
        'السيد', 'السيدة', 'الأستاذ', 'الأستاذة', 'الدكتور', 'الدكتورة',
        'المهندس', 'المهندسة', 'الشيخ', 'الحاج', 'الحاجة', 'أخي', 'أختي',
        'عزيزي', 'عزيزتي', 'مع', 'اسمي', 'انا', 'أنا',
    ];

    /**
     * The same job in English, for the same reason.
     *
     * @var list<string>
     */
    private const LATIN_NAME_MARKERS = [
        'mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'sir', 'madam',
        'my name is', 'this is', 'regards', 'sincerely', 'from',
    ];

    /**
     * Words that make a line an address rather than a sentence.
     *
     * @var list<string>
     */
    private const ADDRESS_MARKERS = [
        'street', 'st.', 'road', 'rd.', 'avenue', 'ave', 'building', 'bldg',
        'floor', 'apartment', 'apt', 'flat', 'block', 'postcode', 'zip',
        'شارع', 'طريق', 'عمارة', 'مبنى', 'الدور', 'شقة', 'بلوك', 'ص.ب',
    ];

    /**
     * Removes what it can name, and omits the lines it cannot.
     *
     * @param  list<string>  $declared  Values the caller KNOWS are personal —
     *                                  names, addresses, contact details it
     *                                  read off the record.
     */
    public function redact(string $text, array $declared = []): string
    {
        $clean = $this->removeDeclared($text, $declared);
        $clean = $this->removePatterns($clean);

        return $this->omitWhatIsLeft($clean);
    }

    /**
     * @param  list<string>  $declared
     */
    private function removeDeclared(string $text, array $declared): string
    {
        /*
         * Longest first. "Hana Yousef" and "Hana" can both be declared, and
         * removing the short one first leaves " Yousef" behind — a surname on
         * its own, which is still a name.
         */
        $values = array_values(array_filter(
            array_map(static fn (string $v): string => trim($v), $declared),
            static fn (string $v): bool => mb_strlen($v) >= 2,
        ));

        usort($values, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($values as $value) {
            $text = (string) preg_replace(
                '/'.preg_quote($value, '/').'/iu',
                self::REDACTED,
                $text,
            );
        }

        return $text;
    }

    private function removePatterns(string $text): string
    {
        $patterns = [
            // Email, before phone: an address containing digits must not be
            // half-eaten by the number rule first.
            '/[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.[A-Za-z]{2,}/u',
            // Ticket and customer references, both scripts' digits.
            '/\b(?:TKT|CUS)-[\p{N}]{4,}\b/u',
            /*
             * Phone numbers: seven or more digits, allowing the spaces,
             * dashes, dots and brackets people write them with. Seven rather
             * than ten, because a local number is shorter than an
             * international one and the whole point is to be generous here.
             */
            '/\+?[\p{N}][\p{N}\s().-]{6,}[\p{N}]/u',
        ];

        foreach ($patterns as $pattern) {
            $text = (string) preg_replace($pattern, self::REDACTED, $text);
        }

        return $text;
    }

    /**
     * Drops any line that still looks like it names or locates a person.
     *
     * Line by line rather than word by word, because the unit somebody reads
     * is a line and a sentence with one word blanked out still says where they
     * live. Marked rather than deleted, so a model is not handed prose with an
     * invisible hole in it and left to fill the gap itself.
     */
    private function omitWhatIsLeft(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        $kept = array_map(function (string $line): string {
            return $this->looksPersonal($line) ? self::OMITTED : $line;
        }, $lines);

        return implode("\n", $kept);
    }

    private function looksPersonal(string $line): bool
    {
        $lower = mb_strtolower($line);

        foreach (self::ADDRESS_MARKERS as $marker) {
            // An address marker plus a number is a street address, not a
            // sentence mentioning a road.
            if (str_contains($lower, mb_strtolower($marker)) && preg_match('/[\p{N}]/u', $line) === 1) {
                return true;
            }
        }

        foreach (self::ARABIC_NAME_MARKERS as $marker) {
            if (preg_match('/(?<![\p{L}])'.preg_quote($marker, '/').'\s+\p{L}{2,}/u', $line) === 1) {
                return true;
            }
        }

        foreach (self::LATIN_NAME_MARKERS as $marker) {
            /*
             * The MARKER is matched case-insensitively; the word after it is
             * not. "regards Hana" is a signature and "regards" on its own is a
             * word — the capital is the only thing separating them, so the
             * insensitivity is scoped to the marker with an inline group
             * rather than applied to the whole pattern.
             */
            $pattern = '/(?<![\p{L}])(?i:'.preg_quote($marker, '/').')\.?\s+\p{Lu}\p{L}+/u';

            if (preg_match($pattern, $line) === 1) {
                return true;
            }
        }

        return false;
    }
}
