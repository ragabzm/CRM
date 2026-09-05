<?php

declare(strict_types=1);

namespace App\Modules\Channels\Jobs;

use App\Modules\Channels\Contracts\ChannelTransportFailure;
use App\Modules\Channels\Domain\Outbound\ChannelSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one WhatsApp or SMS reply, off the request.
 *
 * ONE job for both channels, sharing the retry policy and the delivery states
 * — that is the story's "one implementation, two configurations" made real
 * rather than promised.
 *
 * Queued so a provider outage never becomes a ticket outage. An agent pressing
 * Send must not wait on Meta, and a ticket must not be refused because a
 * gateway is down: the reply is written either way, and delivering it is a
 * separate promise this job keeps.
 *
 * Two failure kinds, and the difference is the whole design:
 *
 *   temporary — the provider, the network, a rate limit. Retried with backoff.
 *   permanent — a number not on WhatsApp, a disabled channel, a body the
 *               provider refuses. Failed immediately: retrying burns four
 *               attempts on a message that can never land and delays the
 *               moment anybody finds out.
 */
final class SendChannelMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Five attempts across roughly ten minutes.
     *
     * The same policy the mail job uses, deliberately: an agent should not
     * have to learn that a WhatsApp reply gives up sooner than an email one.
     */
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300];

    /** Long enough for a slow gateway, short enough not to hold a worker. */
    public int $timeout = 30;

    public function __construct(
        private readonly string $messageId,
        private readonly string $channel,
        private readonly ?string $channelAccountId,
        private readonly string $to,
        private readonly string $body,
    ) {}

    public function handle(ChannelSender $sender): void
    {
        try {
            $result = $sender->send($this->channel, $this->channelAccountId, $this->to, $this->body);
        } catch (ChannelTransportFailure $failure) {
            if ($failure->temporary && $this->attempts() < $this->tries) {
                /*
                 * Left queued, not marked failed. A message the queue is still
                 * going to try must not show an agent a Retry button — they
                 * would press it and send the customer the same words twice.
                 */
                $this->release($this->backoff[$this->attempts() - 1] ?? 300);

                return;
            }

            $this->markFailed($failure->getMessage());

            return;
        } catch (Throwable $e) {
            // An unexpected failure is treated as permanent on the last
            // attempt and retried before that, same as the provider's own.
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 300);

                return;
            }

            $this->markFailed($e->getMessage());

            return;
        }

        DB::table('ticket_messages')->where('id', $this->messageId)->update([
            'delivery_state' => 'sent',
            'sent_at' => now(),
            /*
             * The PROVIDER's id, where it gave one. It is what a delivery
             * receipt arrives quoting, and without it a receipt cannot be
             * matched to the message it is about.
             */
            'provider_message_id' => $result['provider_message_id'] === ''
                ? null
                : $result['provider_message_id'],
            'updated_at' => now(),
        ]);
    }

    private function markFailed(string $why): void
    {
        DB::table('ticket_messages')->where('id', $this->messageId)->update([
            'delivery_state' => 'failed',
            'updated_at' => now(),
        ]);

        /*
         * Logged with the message id, so an administrator reading the log can
         * find the conversation. The agent sees it in the timeline with Retry
         * and Edit — UX-09 — and the customer sees nothing, because nothing
         * was sent.
         */
        Log::warning('Channel message could not be delivered.', [
            'message_id' => $this->messageId,
            'channel' => $this->channel,
            'reason' => $why,
        ]);
    }
}
