<?php

declare(strict_types=1);

namespace App\Modules\Channels\Contracts;

/**
 * One inbound message, in the only shape the spine understands.
 *
 * Every adapter's job ends here: it turns whatever its provider sent into this,
 * and the pipeline that follows has no idea which transport it came from. That
 * is the whole point of the seam — the correlation order, the idempotency key,
 * the department rule and the quarantine behave identically for email and for
 * a web form because none of them can tell the difference.
 *
 * Mail-only facts (headers, thread ids) travel in `headers` and `threadIds`
 * rather than as first-class fields, so adding WhatsApp does not mean adding
 * five nullable email columns to a shared DTO.
 */
final readonly class ChannelPayload
{
    /**
     * @param  array<string, list<string>>  $threadRules
     *         Named groups of provider ids this message replies to, in the
     *         order they should be tried. The NAME is what the correlation
     *         trace records, so a transport that distinguishes `in_reply_to`
     *         from `references` keeps both names in its history rather than
     *         collapsing them into one indistinguishable "thread" rule.
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $rawPayload
     * @param  list<string>  $attachmentIds Already-uploaded attachments to re-own onto the ticket.
     */
    public function __construct(
        public string $channel,
        public string $providerMessageId,
        public ChannelIdentifier $sender,
        public string $subject,
        public string $body,
        public array $threadRules = [],
        public array $headers = [],
        public array $rawPayload = [],
        public array $attachmentIds = [],
        public ?string $recipientIdentifier = null,
        /**
         * The category the sender chose, where the channel asked for one.
         *
         * The web form does; email does not, and nothing is inferred for it —
         * a guessed category is worse than none, because it looks like
         * somebody decided.
         */
        public ?int $categoryId = null,
        public ?string $channelAccountId = null,
        /**
         * Set when the message announces itself as machine-generated.
         *
         * The one thing this changes: no acknowledgement is sent. Replying to
         * an auto-reply produces an auto-reply, and two systems will do that to
         * each other until somebody notices the mailbox.
         */
        public bool $isAutomated = false,
    ) {}
}
