<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

/**
 * The three headers that keep one conversation looking like one conversation.
 *
 * Our own thread holds together on `ticket_id` whatever happens here. These are
 * for the CUSTOMER's mail client: a message whose `In-Reply-To` does not name
 * something it has already seen is filed as a new conversation, and one problem
 * becomes eight unrelated emails in their inbox.
 *
 * `References` accumulates the whole chain rather than only the parent, because
 * that is what lets a client rebuild the thread when a message in the middle
 * was deleted or never arrived.
 */
final readonly class ThreadHeaders
{
    /**
     * @param  list<string>  $references  Oldest first, as RFC 5322 requires.
     */
    private function __construct(
        public string $messageId,
        public ?string $inReplyTo,
        public array $references,
    ) {}

    /**
     * Builds the headers for a new outbound message in a thread.
     *
     * @param  string|null  $parentMessageId  The last message we sent or received.
     * @param  list<string>  $parentReferences
     */
    public static function forReply(
        string $domain,
        string $ticketId,
        string $messageId,
        ?string $parentMessageId,
        array $parentReferences,
    ): self {
        /*
         * Derived from our own ids, not random: a Message-ID we can recognise
         * later is what lets Story 5.2 correlate a reply back to this exact
         * message rather than guessing from the subject.
         */
        $ownId = sprintf('<%s.%s@%s>', $ticketId, $messageId, $domain);

        $references = $parentReferences;

        if ($parentMessageId !== null && ! in_array($parentMessageId, $references, true)) {
            $references[] = $parentMessageId;
        }

        return new self($ownId, $parentMessageId, array_values($references));
    }

    /**
     * The headers as a mail transport wants them.
     *
     * `References` omitted entirely when empty rather than sent blank: an empty
     * References header is malformed, and some clients discard the whole
     * message rather than the header.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $headers = ['Message-ID' => $this->messageId];

        if ($this->inReplyTo !== null && $this->inReplyTo !== '') {
            $headers['In-Reply-To'] = $this->inReplyTo;
        }

        if ($this->references !== []) {
            // Space-separated, oldest first.
            $headers['References'] = implode(' ', $this->references);
        }

        return $headers;
    }

    /** @return list<string> */
    public static function parseReferences(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/', trim($raw)) ?: [],
            static fn (string $part): bool => $part !== '',
        ));
    }
}
