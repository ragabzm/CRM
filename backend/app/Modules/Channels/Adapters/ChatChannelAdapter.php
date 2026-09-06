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
 * A chat conversation, expressed as a channel.
 *
 * It goes through the same spine as email, the form and WhatsApp: the same
 * idempotency claim, the same correlation, the same department ladder, the
 * same commands. A chat that had its own short path would produce tickets that
 * are subtly different from every other ticket, and nobody would find out
 * until an agent asked why one of them has no history.
 *
 * THE IDENTIFIER IS THE INTERESTING PART. A visitor may have typed an email
 * address into the widget, or may have typed nothing at all. When they gave
 * one, it is used — so the conversation joins the record they already had, and
 * a customer who emailed on Monday and chatted on Tuesday is one customer.
 * When they gave nothing, the conversation id is the identifier, and the
 * honest consequence is recorded on the enum: somebody who comes back tomorrow
 * with a new session is a new record, because we have no way to know they are
 * the same person.
 */
final class ChatChannelAdapter implements ChannelAdapter
{
    public const CHANNEL = 'chat';

    public function channel(): string
    {
        return self::CHANNEL;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function parse(array $raw): ChannelPayload
    {
        $conversationId = trim((string) ($raw['conversation_id'] ?? ''));
        $body = (string) ($raw['body'] ?? '');

        if ($conversationId === '') {
            throw new UnparseableChannelPayload('The chat message named no conversation.');
        }

        if (trim($body) === '') {
            throw new UnparseableChannelPayload('The chat message was empty.');
        }

        $name = trim((string) ($raw['visitor_name'] ?? ''));

        return new ChannelPayload(
            channel: self::CHANNEL,
            // Minted here, never accepted from the widget: a caller must not
            // be able to claim an id belonging to somebody else's message or
            // replay one of their own.
            providerMessageId: (string) Str::ulid(),
            sender: $this->identifierFor($conversationId, $raw),
            /*
             * The subject is the FIRST THING THEY SAID, trimmed.
             *
             * A chat has no subject line, and the alternatives are worse: a
             * fixed "Live chat" makes every conversation look identical in the
             * queue, and asking the visitor for one turns a quick question
             * into a form.
             */
            subject: $this->subjectFrom($body),
            body: $body,
            /*
             * The conversation id, as a thread rule.
             *
             * This is what makes every message after the first append to the
             * ticket the first one created, using the SAME correlator every
             * other channel uses rather than a chat-shaped exception inside it.
             */
            threadRules: ['chat_conversation' => [$conversationId]],
            headers: ['conversation_id' => $conversationId, 'visitor_name' => $name],
            rawPayload: ['conversation_id' => $conversationId, 'body' => $body],
            channelAccountId: self::accountId(),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function identifierFor(string $conversationId, array $raw): ChannelIdentifier
    {
        $name = trim((string) ($raw['visitor_name'] ?? ''));
        $given = trim((string) ($raw['visitor_identifier'] ?? ''));

        if ($given !== '') {
            $kind = str_contains($given, '@') ? ContactKind::Email : ContactKind::Phone;

            return new ChannelIdentifier(
                $kind->value,
                IdentifierNormaliser::normalise($kind, $given),
                $name,
            );
        }

        /*
         * Nothing but a session. Still a customer record, because a
         * conversation with no owner cannot become a ticket — and a ticket is
         * what the agent has to answer.
         */
        return new ChannelIdentifier(ContactKind::Chat->value, $conversationId, $name);
    }

    private function subjectFrom(string $body): string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $body));

        return mb_substr($line, 0, 120);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function rawText(array $raw): string
    {
        return (string) ($raw['body'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function fingerprint(array $raw): string
    {
        return hash('sha256', self::CHANNEL.'|'.json_encode($raw));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function bestEffortSubject(array $raw): string
    {
        return $this->subjectFrom((string) ($raw['body'] ?? ''));
    }

    /** The single active chat account, if one is configured. */
    public static function accountId(): ?string
    {
        $id = DB::table('channel_accounts')
            ->where('channel', self::CHANNEL)
            ->where('is_active', true)
            ->value('id');

        return $id === null ? null : (string) $id;
    }
}
