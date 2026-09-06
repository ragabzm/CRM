<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain;

/**
 * Whether anything may leave this deployment at all.
 *
 * The setting an administrator reaches for when the answer to "where does our
 * customers' text go?" has to be "nowhere". `Off` is not a way of disabling
 * the five capabilities one at a time — it is a statement about the boundary
 * of the deployment, and it holds even if every capability is switched on.
 */
enum TransmissionMode: string
{
    /**
     * Nothing leaves. Every capability degrades exactly as it does when the
     * provider is unreachable, which is the behaviour the whole product is
     * already correct under.
     */
    case Off = 'off';

    /**
     * Sanitised content may be sent to the configured provider.
     *
     * Named `redacted` rather than `on`, because what leaves is never the
     * customer's text — it is what survived the sanitiser, and the name is the
     * one place an administrator reads that before choosing it.
     */
    case Redacted = 'redacted';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
