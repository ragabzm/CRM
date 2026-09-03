<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain\Inbound;

/**
 * An email, after somebody has made sense of it.
 *
 * Flat and immutable so nothing downstream has to hold a parser object open, and
 * so the correlation rules read fields rather than re-parsing the same bytes
 * four times.
 */
final readonly class ParsedMail
{
    /**
     * @param  list<string>  $references
     * @param  list<array{filename: string, mime: string, bytes: string}>  $attachments
     */
    public function __construct(
        public ?string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public string $fromAddress,
        public string $fromName,
        public string $subject,
        public string $body,
        public array $attachments,
        /**
         * Set when the message announces itself as machine-generated.
         *
         * The one header set that must never be acknowledged: replying to an
         * auto-reply produces an auto-reply, and two systems will happily do
         * that to each other until somebody notices the mailbox.
         */
        public bool $isAutomated,
    ) {}
}
