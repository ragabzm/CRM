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
use Illuminate\Support\Str;

/**
 * The public form, expressed as a channel.
 *
 * It goes through the same spine as email — the same idempotency claim, the
 * same correlation, the same department rule, the same commands — because a
 * ticket raised on the form and a ticket raised by email are the same thing to
 * everybody downstream. A form that had its own short path is a form whose
 * tickets are subtly different from every other ticket, and nobody finds out
 * until an agent asks why one of them has no history.
 *
 * Two things are deliberately not the client's to decide:
 *
 *   The message id. It is minted here, so a caller cannot claim an id that
 *   belongs to somebody else's submission or replay one of their own.
 *
 *   The identifier kind. `contact` is one field because a person has one way
 *   they prefer to be reached, and asking them to first classify it is a form
 *   field that exists for the database's benefit.
 */
final class WebFormChannelAdapter implements ChannelAdapter
{
    public const CHANNEL = 'web_form';

    public function channel(): string
    {
        return self::CHANNEL;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function parse(array $raw): ChannelPayload
    {
        $contact = trim((string) ($raw['contact'] ?? ''));

        if ($contact === '') {
            throw new UnparseableChannelPayload('The submission carried no way to reach the sender.');
        }

        $kind = self::kindOf($contact);

        return new ChannelPayload(
            channel: self::CHANNEL,
            // Minted, never accepted from the client. See the class note.
            providerMessageId: (string) Str::ulid(),
            sender: new ChannelIdentifier(
                $kind->value,
                IdentifierNormaliser::normalise($kind, $contact),
                trim((string) ($raw['name'] ?? '')),
            ),
            subject: trim((string) ($raw['subject'] ?? '')),
            body: (string) ($raw['message'] ?? ''),
            headers: ['contact_as_given' => $contact],

            /*
             * A first-class field, not a header.
             *
             * It was in the headers bag and nothing read it, so the form asked
             * a person to choose a category and then threw the answer away —
             * every web-form ticket arrived uncategorised, and the
             * auto-assignment mapping that keys on category could never fire
             * for one.
             */
            categoryId: is_numeric($raw['category_id'] ?? null) ? (int) $raw['category_id'] : null,
            rawPayload: $this->withoutSecrets($raw),
            attachmentIds: array_values(array_filter(
                (array) ($raw['attachment_ids'] ?? []),
                static fn (mixed $id): bool => is_string($id) && $id !== '',
            )),
            channelAccountId: self::accountId(),
        );
    }

    /**
     * Email when it looks like one, phone otherwise.
     *
     * Validation has already refused anything that is neither, so this is a
     * classification rather than a check.
     */
    public static function kindOf(string $contact): ContactKind
    {
        return str_contains($contact, '@') ? ContactKind::Email : ContactKind::Phone;
    }

    /** The single web-form account, if one is configured. */
    public static function accountId(): ?string
    {
        $id = DB::table('channel_accounts')
            ->where('channel', self::CHANNEL)
            ->where('is_active', true)
            ->value('id');

        return $id === null ? null : (string) $id;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function rawText(array $raw): string
    {
        return json_encode($this->withoutSecrets($raw), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
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
        $subject = trim((string) ($raw['subject'] ?? ''));

        return $subject === '' ? null : mb_substr($subject, 0, 512);
    }

    /**
     * The submission minus the fields that only exist to catch robots.
     *
     * They are not part of what the person wrote, and keeping the honeypot's
     * contents in a permanent record would make the trap visible to anybody
     * reading quarantine.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function withoutSecrets(array $raw): array
    {
        unset($raw['hp_company'], $raw['rendered_at'], $raw['session_token']);

        return $raw;
    }
}
