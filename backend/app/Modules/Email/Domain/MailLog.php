<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

use App\Modules\Integrations\Domain\ExchangeLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one writer of the mail log.
 *
 * Every write goes through here so an attempt cannot be recorded in two shapes,
 * and so the one rule that matters is enforced in one place: **the log never
 * throws**. A failure to write the record of a send must not become a failure
 * to send — that would turn a diagnostic into an outage, and it would happen
 * exactly when the system is already under strain.
 *
 * The ROWS live in the one exchange log, not in a table of Email's own. Story
 * 12.3 made that log general, and a second table with the same five columns
 * would mean an administrator asking "did anything reach the outside world
 * last night?" has to know which integration to ask. What stays Email's is the
 * vocabulary: at this boundary a successful exchange is still a `sent` email.
 */
final class MailLog
{
    public function __construct(private readonly ExchangeLog $exchanges) {}

    /** Email's name in the shared log. */
    public const INTEGRATION = 'email';

    /**
     * Records that a send is about to be attempted.
     *
     * Written BEFORE the attempt, so a send that hangs or crashes the worker
     * still leaves evidence that it was tried. A log written only on completion
     * is silent about precisely the failures that need explaining.
     */
    public function queued(
        string $address,
        string $provider,
        ?string $messageId = null,
        ?string $ticketId = null,
        ?string $subject = null,
        int $attempt = 1,
    ): ?string {
        try {
            return $this->exchanges->queued(
                integration: self::INTEGRATION,
                target: $address,
                // No headers and NO BODY. The log records that a message was
                // sent and to whom, never what it said: a mail log holding
                // message bodies is a copy of every conversation, with its own
                // retention and its own export.
                headers: [],
                body: [],
                attempt: $attempt,
                context: array_filter([
                    'provider' => $provider,
                    'subject' => $subject,
                    'message_id' => $messageId,
                    'ticket_id' => $ticketId,
                ], static fn (?string $v): bool => $v !== null),
            );
        } catch (Throwable $e) {
            return $this->swallow($e, 'write');
        }
    }

    public function sent(?string $entryId, int $durationMs): void
    {
        if ($entryId === null) {
            return;
        }

        try {
            $this->exchanges->succeeded($entryId, null, [], $durationMs);
        } catch (Throwable $e) {
            $this->swallow($e, 'update');
        }
    }

    public function failed(?string $entryId, string $error, ?string $providerCode, int $durationMs): void
    {
        if ($entryId === null) {
            return;
        }

        try {
            $this->exchanges->failed(
                $entryId,
                null,
                // The provider's own words. A generic "send failed" tells an
                // administrator nothing they can act on.
                $error,
                $durationMs,
                $providerCode === null ? [] : ['provider_code' => $providerCode],
            );
        } catch (Throwable $e) {
            $this->swallow($e, 'update');
        }
    }

    /**
     * Swallowed, and said so in the application log.
     *
     * A failure to RECORD a send must never become a failure to send.
     * Returning null lets the caller carry on; the later `sent`/`failed` calls
     * become no-ops, and the email still goes out.
     */
    private function swallow(Throwable $e, string $what): ?string
    {
        Log::warning('Could not '.$what.' the mail log.', [
            'reason' => $e->getMessage(),
            'consequence' => 'The send itself is unaffected; this attempt has no log entry.',
        ]);

        return null;
    }
}
