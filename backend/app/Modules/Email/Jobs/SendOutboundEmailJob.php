<?php

declare(strict_types=1);

namespace App\Modules\Email\Jobs;

use App\Modules\Email\Contracts\MailTransportFailure;
use App\Modules\Email\Domain\OutboundMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one email, off the request.
 *
 * Queued so that a provider outage never becomes a ticket outage. An agent
 * pressing Send must not wait on a third party, and a customer creating a
 * ticket must not be refused because an SMTP host is down — the reply is
 * written either way, and the sending is a separate promise this job keeps.
 *
 * Two failure kinds, and the difference is the whole design:
 *
 *   temporary — the provider, the network, a rate limit. Retried with backoff.
 *   permanent — a malformed address, a refused sender, a disabled channel.
 *               Failed immediately: retrying burns the provider's reputation
 *               on a message that can never land, and delays the moment
 *               anybody finds out.
 */
final class SendOutboundEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Five attempts across roughly ten minutes.
     *
     * Long enough to ride out a provider restart or a brief rate limit; short
     * enough that a genuinely dead provider surfaces as `failed` while the
     * agent is still at their desk, rather than the next morning.
     */
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300];

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $toAddress,
        public readonly string $toName,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $headers,
        public readonly string $locale,
        public readonly ?string $messageId = null,
        public readonly ?string $ticketId = null,
    ) {}

    public function handle(OutboundMailer $mailer): void
    {
        try {
            $mailer->send(
                $this->toAddress,
                $this->toName,
                $this->subject,
                $this->body,
                $this->headers,
                $this->locale,
                $this->messageId,
                $this->ticketId,
                $this->attempts(),
            );

            $this->markMessage('sent');
        } catch (MailTransportFailure $failure) {
            if (! $failure->retryable) {
                /*
                 * Done. `fail()` rather than rethrowing, so the queue records
                 * one terminal failure instead of four pointless retries of
                 * something that cannot succeed.
                 */
                $this->markMessage('failed');
                $this->fail($failure);

                return;
            }

            // Out of attempts: this is now terminal however temporary the
            // cause was.
            if ($this->attempts() >= $this->tries) {
                $this->markMessage('failed');
            }

            /*
             * Rethrown, never swallowed. The queue needs the exception to
             * schedule the next attempt, and an agent needs the eventual
             * failure to reach the screen rather than disappearing into a log.
             */
            throw $failure;
        }
    }

    /**
     * Reflects the outcome on the message an agent is looking at.
     *
     * A raw update rather than the Tickets model: this job lives in Email (T4)
     * and Tickets is T3, so it may depend downward — but loading, mutating and
     * saving another module's aggregate would make this job break whenever that
     * model changed. One column, by id.
     */
    private function markMessage(string $state): void
    {
        if ($this->messageId === null) {
            return;
        }

        try {
            DB::table('ticket_messages')
                ->where('id', $this->messageId)
                ->update(['delivery_state' => $state]);
        } catch (Throwable $e) {
            Log::warning('Could not record the delivery state of a message.', [
                'message_id' => $this->messageId,
                'state' => $state,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
