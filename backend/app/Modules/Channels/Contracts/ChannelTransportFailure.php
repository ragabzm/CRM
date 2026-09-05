<?php

declare(strict_types=1);

namespace App\Modules\Channels\Contracts;

use RuntimeException;

/**
 * A provider refused or could not be reached.
 *
 * Carries whether it is worth trying again, and that distinction is the whole
 * point of the class:
 *
 *   temporary — the provider, the network, a rate limit. Retried with backoff.
 *   permanent — a number that is not on WhatsApp, a refused sender, a message
 *               longer than the provider accepts. Failed immediately, because
 *               retrying burns the provider's goodwill on a message that can
 *               never land and delays the moment anybody finds out.
 *
 * A transport that cannot tell says temporary. Retrying something permanent
 * costs four attempts; failing something temporary loses the message.
 */
final class ChannelTransportFailure extends RuntimeException
{
    private function __construct(string $message, public readonly bool $temporary)
    {
        parent::__construct($message);
    }

    public static function temporary(string $why): self
    {
        return new self($why, true);
    }

    public static function permanent(string $why): self
    {
        return new self($why, false);
    }
}
