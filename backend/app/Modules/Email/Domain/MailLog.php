<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

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
 */
final class MailLog
{
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
        return $this->write([
            'direction' => MailLogEntry::OUTBOUND,
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
            'provider' => $provider,
            'address' => $address,
            'subject' => $subject,
            'status' => MailLogEntry::QUEUED,
            'attempt' => $attempt,
        ]);
    }

    public function sent(?string $entryId, int $durationMs): void
    {
        $this->update($entryId, [
            'status' => MailLogEntry::SENT,
            'duration_ms' => $durationMs,
        ]);
    }

    public function failed(?string $entryId, string $error, ?string $providerCode, int $durationMs): void
    {
        $this->update($entryId, [
            'status' => MailLogEntry::FAILED,
            // The provider's own words. A generic "send failed" tells an
            // administrator nothing they can act on.
            'error' => mb_substr($error, 0, 2000),
            'provider_code' => $providerCode,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes): ?string
    {
        try {
            $entry = new MailLogEntry;

            $entry->forceFill([...$attributes, 'occurred_at' => now()])->save();

            return (string) $entry->getKey();
        } catch (Throwable $e) {
            /*
             * Swallowed, and said so in the application log.
             *
             * A failure to record a send must never become a failure to send.
             * Returning null lets the caller carry on; the later `update` calls
             * are no-ops, and the email still goes out.
             */
            Log::warning('Could not write to the mail log.', [
                'reason' => $e->getMessage(),
                'consequence' => 'The send itself is unaffected; this attempt has no log entry.',
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function update(?string $entryId, array $attributes): void
    {
        if ($entryId === null) {
            return;
        }

        try {
            MailLogEntry::query()->whereKey($entryId)->update($attributes);
        } catch (Throwable $e) {
            Log::warning('Could not update the mail log.', ['reason' => $e->getMessage()]);
        }
    }
}
