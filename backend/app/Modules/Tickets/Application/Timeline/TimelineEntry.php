<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Timeline;

/**
 * One thing that happened for a customer.
 *
 * Deliberately flat and pre-shaped: the frontend renders a list of these
 * directly, so the query decides what a row says rather than each client
 * deciding for itself and disagreeing.
 */
final class TimelineEntry
{
    public const TICKET_OPENED = 'ticket_opened';

    public const MESSAGE_INBOUND = 'message_inbound';

    public const MESSAGE_OUTBOUND = 'message_outbound';

    public function __construct(
        /** Stable per (kind, source row) — the source row's own id. */
        public readonly string $id,
        public readonly string $kind,
        public readonly string $ticketId,
        /** The human reference, so a row is quotable without a second lookup. */
        public readonly string $ticketRef,
        public readonly string $occurredAt,
        /** First ~140 characters of a message. Null for a ticket opening. */
        public readonly ?string $preview,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'ticket_id' => $this->ticketId,
            'ticket_ref' => $this->ticketRef,
            'occurred_at' => $this->occurredAt,
            'preview' => $this->preview,
        ];
    }
}
