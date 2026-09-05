<?php

declare(strict_types=1);

namespace App\Modules\Channels\Adapters;

use App\Modules\Channels\Contracts\ChannelAdapter;
use App\Modules\Channels\Contracts\ChannelIdentifier;
use App\Modules\Channels\Contracts\ChannelPayload;
use App\Modules\Channels\Exceptions\UnparseableChannelPayload;
use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Customers\Domain\IdentifierNormaliser;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp and SMS, which are the same adapter twice.
 *
 * ONE class serving both channels, constructed with which one it is. That is
 * the story's claim — "one implementation with two configurations" — made
 * structural rather than promised: there is no WhatsApp branch and no SMS
 * branch anywhere below, because the only thing that differs between them is
 * which `ContactKind` a sender's number is recorded as.
 *
 * Both providers speak the same shape after normalisation: a number, a body,
 * and an id the provider assigned. What actually differs between Twilio and
 * Meta is signature verification and field names, and that belongs in the
 * webhook controller which knows the provider — not here, which knows the
 * channel.
 *
 * Numbers are normalised to E.164 AT THE BOUNDARY, in and out. A customer's
 * phone number and their WhatsApp number may legitimately be the same digits
 * on the same record, and matching must not depend on which one the provider
 * happened to report.
 */
final class PhoneChannelAdapter implements ChannelAdapter
{
    public const WHATSAPP = 'whatsapp';

    public const SMS = 'sms';

    public function __construct(private readonly string $channel) {}

    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function parse(array $raw): ChannelPayload
    {
        $from = trim((string) ($raw['from'] ?? ''));
        $body = (string) ($raw['body'] ?? '');

        if ($from === '') {
            throw new UnparseableChannelPayload('The message carried no sender number.');
        }

        $normalised = IdentifierNormaliser::normalise(ContactKind::Phone, $from);

        if ($normalised === '') {
            throw new UnparseableChannelPayload("The sender [{$from}] is not a phone number.");
        }

        return new ChannelPayload(
            channel: $this->channel,
            providerMessageId: $this->providerMessageId($raw),
            sender: new ChannelIdentifier(
                /*
                 * A WhatsApp number is its own identifier kind, beside the
                 * phone number rather than instead of it. The same digits are
                 * often both — one person, one record — and recording them
                 * separately is what lets "reply on the channel it arrived on"
                 * be a fact rather than a guess.
                 */
                $this->channel === self::WHATSAPP
                    ? ContactKind::WhatsApp->value
                    : ContactKind::Phone->value,
                $normalised,
                trim((string) ($raw['profile_name'] ?? '')),
            ),
            // No subject on either transport. A ticket needs one, so the
            // spine falls back to a marker rather than inventing a summary.
            subject: '',
            body: $body,
            headers: ['provider' => $raw['provider'] ?? null, 'from_as_given' => $from],
            rawPayload: $raw,
            recipientIdentifier: isset($raw['to']) ? (string) $raw['to'] : null,
            channelAccountId: $this->accountFor($raw),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function rawText(array $raw): string
    {
        return json_encode($raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function fingerprint(array $raw): string
    {
        return 'sha256:'.hash('sha256', $this->rawText($raw));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function bestEffortSubject(array $raw): ?string
    {
        $body = trim((string) ($raw['body'] ?? ''));

        // The first line, for a quarantine list an administrator can scan.
        // These transports have no subject of their own.
        return $body === '' ? null : mb_substr(strtok($body, "\n") ?: $body, 0, 512);
    }

    /**
     * The provider's own id, or a hash of the payload.
     *
     * @param  array<string, mixed>  $raw
     */
    private function providerMessageId(array $raw): string
    {
        foreach (['message_id', 'MessageSid', 'sid', 'id'] as $field) {
            $value = $raw[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $this->fingerprint($raw);
    }

    /**
     * Which configured account received it, matched on the number dialled.
     *
     * @param  array<string, mixed>  $raw
     */
    private function accountFor(array $raw): ?string
    {
        $to = IdentifierNormaliser::normalise(ContactKind::Phone, (string) ($raw['to'] ?? ''));

        if ($to === '') {
            return null;
        }

        /*
         * Matched on the normalised number inside the account's own
         * configuration. A business running two WhatsApp numbers into two
         * departments needs this to be the number and not the channel.
         */
        foreach (DB::table('channel_accounts')->where('channel', $this->channel)->get() as $account) {
            $config = json_decode((string) $account->provider_config, true);
            $number = is_array($config) ? (string) ($config['number'] ?? '') : '';

            if ($number !== '' && IdentifierNormaliser::normalise(ContactKind::Phone, $number) === $to) {
                return (string) $account->id;
            }
        }

        return null;
    }
}
