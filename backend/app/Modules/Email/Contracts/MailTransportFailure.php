<?php

declare(strict_types=1);

namespace App\Modules\Email\Contracts;

use RuntimeException;
use Throwable;

/**
 * A send that did not happen.
 *
 * Carries whether trying again could help, which is the only question the
 * retry logic actually has. A refused recipient will be refused identically in
 * five minutes; a connection reset will not.
 *
 * Deciding this HERE rather than in the job means each adapter answers it about
 * its own provider's errors, which is the only place that knowledge exists.
 */
final class MailTransportFailure extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly ?string $providerCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** The provider, the network, or a rate limit — worth trying again. */
    public static function temporary(string $message, ?string $code = null, ?Throwable $previous = null): self
    {
        return new self($message, true, $code, $previous);
    }

    /**
     * The message itself is wrong — a malformed address, a rejected sender.
     *
     * Retrying is not just useless but harmful: it burns the provider's
     * reputation on a message that can never land, and it delays the moment
     * anyone finds out.
     */
    public static function permanent(string $message, ?string $code = null, ?Throwable $previous = null): self
    {
        return new self($message, false, $code, $previous);
    }
}
