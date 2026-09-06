<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use Illuminate\Support\Str;

/**
 * The only credential a chat visitor ever holds.
 *
 * WHAT IT IS NOT is the important part. It is not a session: it resolves to no
 * user, carries no capability, and passes through none of the authentication
 * guards. It cannot read a ticket, list conversations, or reach any endpoint
 * outside the four the widget calls. Somebody who steals one can read and
 * continue exactly one conversation, until it expires.
 *
 * That narrowness is deliberate and structural. A chat widget is embedded on
 * third-party websites — the least trustworthy place this product runs — and
 * anything it holds should be assumed to be readable by whoever owns that
 * page. So it holds the smallest thing that works.
 *
 * Stored HASHED, verified by hash. The plaintext exists in exactly two places:
 * the response that issued it, and the visitor's browser.
 */
final class ChatToken
{
    /** Long enough that guessing is not a strategy. */
    private const BYTES = 32;

    public static function mint(): string
    {
        return Str::random(self::BYTES * 2);
    }

    /**
     * A fast hash, on purpose.
     *
     * NOT bcrypt. A password hash is slow to make brute force expensive
     * against a low-entropy secret somebody chose; this secret is 64 random
     * characters, so brute force is already impossible and the slowness would
     * only be paid on every poll — several times a minute, per visitor.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
