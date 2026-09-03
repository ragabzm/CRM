<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Contracts\MailTransportFailure;
use App\Modules\Platform\Support\Settings\SettingsRegistry;

/**
 * Sends one email and writes down what happened.
 *
 * Sits between the job and the transport so both stay simple: the job owns
 * retrying, the transport owns talking to a provider, and this owns the two
 * things that must happen either side of a send — the log entry, and the
 * timing that makes a degrading provider visible before anyone complains.
 */
final class OutboundMailer
{
    public function __construct(
        private readonly MailTransport $transport,
        private readonly MailLog $log,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @param  array<string, string>  $headers
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
        ?string $messageId = null,
        ?string $ticketId = null,
        int $attempt = 1,
    ): void {
        if (! $this->enabled()) {
            /*
             * Permanent, not temporary. A disabled channel is a decision
             * somebody made, and retrying it for a day would fill the queue
             * with work that is supposed to not happen.
             */
            throw MailTransportFailure::permanent('The email channel is switched off.');
        }

        // Written BEFORE the attempt: a send that hangs or kills the worker
        // still leaves evidence that it was tried.
        $entry = $this->log->queued(
            address: $toAddress,
            provider: $this->transport->name(),
            messageId: $messageId,
            ticketId: $ticketId,
            subject: $subject,
            attempt: $attempt,
        );

        $started = microtime(true);

        try {
            $this->transport->send($toAddress, $toName, $subject, $body, $headers, $locale);
        } catch (MailTransportFailure $failure) {
            $this->log->failed(
                $entry,
                $failure->getMessage(),
                $failure->providerCode,
                (int) ((microtime(true) - $started) * 1000),
            );

            throw $failure;
        }

        $this->log->sent($entry, (int) ((microtime(true) - $started) * 1000));
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('email.enabled');
    }
}
