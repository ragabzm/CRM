<?php

declare(strict_types=1);

namespace App\Modules\Email\Contracts;

/**
 * How this application hands an email to something that will deliver it.
 *
 * A port, so the provider is a configuration value rather than a dependency.
 * SMTP, Postmark, Mailgun and SES all sit behind this one method; swapping
 * between them changes a setting and nothing else.
 *
 * It also means CI needs no provider at all. `NullMailTransport` is the default
 * and records what it was asked to send, so every test about headers, threading
 * and retries runs without a network — and a test suite that quietly required
 * an SMTP server would be a test suite nobody could run.
 *
 * Primitives only: this contract is read by modules that must not learn the
 * Email module's domain types.
 */
interface MailTransport
{
    /**
     * Delivers one message, or throws.
     *
     * Throwing is the contract. A transport that returned false would let a
     * caller ignore it with `@` or a discarded return value; an exception
     * carries the provider's own diagnosis to the log and the retry decision.
     *
     * @param  array<string, string>  $headers  Message-ID, In-Reply-To, References.
     *
     * @throws MailTransportFailure
     */
    public function send(
        string $toAddress,
        string $toName,
        string $subject,
        string $body,
        array $headers,
        string $locale,
    ): void;

    /** Names the provider, for the log and the admin console. */
    public function name(): string;
}
