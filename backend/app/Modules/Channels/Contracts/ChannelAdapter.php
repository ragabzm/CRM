<?php

declare(strict_types=1);

namespace App\Modules\Channels\Contracts;

/**
 * Everything a transport has to supply, and nothing it is allowed to decide.
 *
 * An adapter turns one provider's request into a `ChannelPayload`. It does not
 * correlate, does not claim ids, does not resolve customers or departments and
 * does not write tickets — `InboundIntake` does all of that identically for
 * every channel. Keeping the seam this narrow is what makes "email and the web
 * form behave the same" a structural fact rather than a promise.
 */
interface ChannelAdapter
{
    /** `email` | `web_form` | `whatsapp` | `sms` | `chat` */
    public function channel(): string;

    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws \App\Modules\Channels\Exceptions\UnparseableChannelPayload
     *         when no message can be made of it. Named in full rather than
     *         imported: a contract that imports its module's concrete classes
     *         hands that dependency to every implementer.
     */
    public function parse(array $raw): ChannelPayload;

    /**
     * An id for a message that carries none.
     *
     * A hash of the payload: two identical deliveries of a malformed message
     * are still one message, and without this each retry would become another
     * quarantine row.
     *
     * @param  array<string, mixed>  $raw
     */
    public function fingerprint(array $raw): string;

    /**
     * The payload as bytes, for quarantine.
     *
     * A parser bug is only diagnosable against what broke it, and replaying
     * once it is fixed is why this is kept. Each transport knows its own
     * answer: mail hands back the RFC 5322 message it was given, a JSON
     * webhook hands back the encoded request.
     *
     * @param  array<string, mixed>  $raw
     */
    public function rawText(array $raw): string;

    /**
     * Whatever subject can be read without a working parser.
     *
     * For a quarantine list an administrator can scan. Null when even that
     * much is unreadable.
     *
     * @param  array<string, mixed>  $raw
     */
    public function bestEffortSubject(array $raw): ?string;
}
