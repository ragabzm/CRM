<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain\Inbound;

use App\Modules\Channels\Contracts\ChannelAdapter;
use App\Modules\Channels\Contracts\ChannelIdentifier;
use App\Modules\Channels\Contracts\ChannelPayload;
use App\Modules\Channels\Exceptions\UnparseableChannelPayload;
use App\Modules\Customers\Domain\ContactKind;
use Throwable;

/**
 * Email, expressed as a channel.
 *
 * All the mail knowledge that used to live in `InboundMailIntake` is here, and
 * nothing else moved with it: the correlation order, the idempotency claim and
 * the department rule now belong to the shared spine, which is the point of the
 * exercise. What remains is the only genuinely mail-specific thing — turning
 * RFC 5322 bytes into a sender, a subject and a body.
 *
 * The two thread rules keep their old names. `in_reply_to` and `references`
 * are what the correlation trace has recorded since Story 5.2, and renaming
 * them would rewrite the meaning of every row already in the table.
 */
final class MailChannelAdapter implements ChannelAdapter
{
    public function __construct(private readonly MailParser $parser) {}

    public function channel(): string
    {
        return 'email';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function parse(array $raw): ChannelPayload
    {
        try {
            $mail = $this->parser->parse($this->rawText($raw));
        } catch (Throwable $e) {
            throw new UnparseableChannelPayload($e->getMessage(), 0, $e);
        }

        return new ChannelPayload(
            channel: 'email',
            /*
             * The provider's id where it gives one, else the message's own
             * Message-ID, else a hash of the bytes. The last is a genuine
             * fallback: a message with no Message-ID is malformed, but two
             * identical deliveries of it are still one email.
             */
            providerMessageId: $this->providerMessageId($raw) ?? $mail->messageId ?? $this->fingerprint($raw),
            sender: new ChannelIdentifier(ContactKind::Email->value, $mail->fromAddress, $mail->fromName),
            subject: $mail->subject,
            body: $mail->body,
            /*
             * Both rules always, even when the headers were empty.
             *
             * An email that arrived with no `In-Reply-To` was still CHECKED for
             * one, and the trace records that it was checked and found nothing.
             * Omitting the empty rule would make a message with no headers
             * indistinguishable from one whose headers pointed at a ticket we
             * could not find — and that difference is the whole reason the
             * losing rules are recorded at all.
             */
            threadRules: [
                'in_reply_to' => $mail->inReplyTo === null ? [] : [$mail->inReplyTo],
                'references' => $mail->references,
            ],
            headers: [
                'provider' => $raw['provider'] ?? null,
                'message_id' => $mail->messageId,
                'in_reply_to' => $mail->inReplyTo,
                'references' => $mail->references,
            ],
            rawPayload: $raw,
            isAutomated: $mail->isAutomated,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function rawText(array $raw): string
    {
        $value = $raw['raw'] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function fingerprint(array $raw): string
    {
        return 'sha256:'.hash('sha256', $this->rawText($raw));
    }

    /**
     * Whatever can be read without a parser, for a list an admin can scan.
     *
     * @param  array<string, mixed>  $raw
     */
    public function bestEffortSubject(array $raw): ?string
    {
        return preg_match('/^Subject:\s*(.+)$/mi', $this->rawText($raw), $m) === 1
            ? mb_substr(trim($m[1]), 0, 512)
            : null;
    }

    /**
     * The id the provider supplied out of band, if any.
     *
     * @param  array<string, mixed>  $raw
     */
    private function providerMessageId(array $raw): ?string
    {
        $value = $raw['provider_message_id'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
