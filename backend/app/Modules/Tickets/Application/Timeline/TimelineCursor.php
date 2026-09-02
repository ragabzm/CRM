<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Timeline;

/**
 * Where the last page stopped.
 *
 * A COMPOSITE of (occurred_at, id), not an offset. An offset shifts the moment
 * anything new arrives: a ticket created between page one and page two pushes
 * every row down by one, so the reader sees an entry twice and never sees
 * another. Keying on the last row's own position means a new entry lands above
 * the window and changes nothing below it.
 *
 * `id` breaks ties, because a ticket and its first message can share a second
 * and (occurred_at) alone would leave their order to the database.
 *
 * Opaque on purpose: base64 of JSON. Not encryption — it holds nothing secret —
 * but a cursor that looks parseable is a cursor somebody builds by hand, and
 * then the format can never change.
 */
final class TimelineCursor
{
    public function __construct(
        public readonly string $occurredAt,
        public readonly string $id,
    ) {}

    public function encode(): string
    {
        return base64_encode(json_encode(['o' => $this->occurredAt, 'i' => $this->id], JSON_THROW_ON_ERROR));
    }

    /**
     * Null for anything unreadable.
     *
     * A malformed cursor returns the FIRST page rather than an error: it is
     * almost always a stale bookmark or a truncated URL, and starting over is
     * what the reader wanted anyway.
     */
    public static function decode(?string $raw): ?self
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $json = base64_decode($raw, true);

        if ($json === false) {
            return null;
        }

        try {
            $parsed = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($parsed) || ! isset($parsed['o'], $parsed['i'])) {
            return null;
        }

        if (! is_string($parsed['o']) || ! is_string($parsed['i'])) {
            return null;
        }

        return new self($parsed['o'], $parsed['i']);
    }
}
