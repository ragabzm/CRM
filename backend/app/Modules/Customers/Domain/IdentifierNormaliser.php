<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

/**
 * Reduces a contact identifier to the form two records are compared by.
 *
 * The point is recognising that "+44 20 7946 0958" and "020 7946 0958" are the
 * same number without ever rewriting what the customer actually gave us. The
 * raw value is stored alongside and is what gets displayed and dialled.
 *
 * This is the single source of truth. The frontend mirrors it so it can warn
 * before a round trip, but the backend decides — a client that normalises
 * differently must not be able to create a record the server considers a
 * duplicate of nothing.
 */
final class IdentifierNormaliser
{
    /**
     * How many trailing digits of a phone number are compared.
     *
     * Ten, so that a number written with a country code and the same number
     * written locally collapse to one key. It is deliberately loose: matching
     * two different people who share the last ten digits offers a duplicate
     * that a human dismisses, while matching neither loses the duplicate
     * entirely — and only one of those failures is expensive.
     */
    public const PHONE_COMPARISON_DIGITS = 10;

    public static function normalise(ContactKind $kind, string $value): string
    {
        return match ($kind) {
            ContactKind::Email => self::email($value),
            ContactKind::Phone => self::phone($value),
        };
    }

    private static function email(string $value): string
    {
        // Lowercased and trimmed, including any whitespace that survived a
        // copy-paste from a mail client.
        return mb_strtolower(preg_replace('/\s+/u', '', trim($value)) ?? '');
    }

    private static function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        /*
         * The TRAILING digits, not the leading ones: a country code and a
         * trunk prefix both sit at the front, and it is the front that differs
         * between two ways of writing one number.
         */
        return strlen($digits) > self::PHONE_COMPARISON_DIGITS
            ? substr($digits, -self::PHONE_COMPARISON_DIGITS)
            : $digits;
    }
}
