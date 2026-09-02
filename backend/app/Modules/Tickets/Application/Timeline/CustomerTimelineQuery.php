<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Timeline;

use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\TicketMessage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * One reverse-chronological feed of everything that happened for a customer.
 *
 * A single UNION ALL, ordered and limited by the database. The alternative —
 * fetching tickets and messages separately and merging in PHP — cannot be
 * paginated correctly: to know the tenth entry overall you would have to read
 * at least ten of each, and on a customer with two years of history that means
 * pulling thousands of rows to render twenty.
 *
 * Both halves are covered by an index on `(customer_id, <time column>)`, so the
 * database sorts a narrow slice rather than the whole history.
 */
final class CustomerTimelineQuery
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 50;

    public function __construct(private readonly ConnectionInterface $db) {}

    public function paginate(string $customerId, ?TimelineCursor $cursor, int $limit): TimelinePage
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));

        /*
         * One extra row, then discarded. That is how `has_more` is answered
         * without a second COUNT over the same union — and a count would be
         * both slower and wrong the moment anything is inserted between the
         * two queries.
         */
        $rows = $this->union($customerId, $cursor)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $items = $rows->map(fn (object $row): TimelineEntry => $this->toEntry($row))->values()->all();

        $last = $rows->last();

        return new TimelinePage(
            items: $items,
            // Only when there is another page. A cursor on the last page would
            // invite one more round trip that returns nothing.
            nextCursor: $hasMore && $last !== null
                ? (new TimelineCursor((string) $last->occurred_at, (string) $last->id))->encode()
                : null,
            hasMore: $hasMore,
        );
    }

    private function union(string $customerId, ?TimelineCursor $cursor): Builder
    {
        $tickets = $this->db->table('tickets')
            ->selectRaw("id, ? as kind, id as ticket_id, reference as ticket_ref, created_at as occurred_at, null as preview", [TimelineEntry::TICKET_OPENED])
            ->where('customer_id', $customerId);

        $this->applyCursor($tickets, $cursor, 'created_at');

        $messages = $this->db->table('ticket_messages as m')
            ->join('tickets as t', 't.id', '=', 'm.ticket_id')
            ->selectRaw(
                "m.id, case when m.direction = ? then ? else ? end as kind, m.ticket_id, t.reference as ticket_ref, m.sent_at as occurred_at, m.body as preview",
                [MessageDirection::Inbound->value, TimelineEntry::MESSAGE_INBOUND, TimelineEntry::MESSAGE_OUTBOUND],
            )
            ->where('m.customer_id', $customerId);

        $this->applyCursor($messages, $cursor, 'm.sent_at', 'm.id');

        /*
         * UNION ALL, not UNION. There is nothing to deduplicate — a ticket row
         * and a message row are different things — and ALL skips the sort-and-
         * compare that DISTINCT would impose on every page.
         */
        return $this->db->query()->fromSub($tickets->unionAll($messages), 'timeline');
    }

    /**
     * Everything strictly before the cursor's position.
     *
     * The composite comparison is what makes paging stable: rows sharing a
     * timestamp are separated by id, so a page boundary can fall between two
     * entries recorded in the same second without repeating or skipping either.
     */
    private function applyCursor(Builder $query, ?TimelineCursor $cursor, string $timeColumn, string $idColumn = 'id'): void
    {
        if ($cursor === null) {
            return;
        }

        $query->where(function (Builder $where) use ($cursor, $timeColumn, $idColumn): void {
            $where->where($timeColumn, '<', $cursor->occurredAt)
                ->orWhere(function (Builder $tie) use ($cursor, $timeColumn, $idColumn): void {
                    $tie->where($timeColumn, '=', $cursor->occurredAt)
                        ->where($idColumn, '<', $cursor->id);
                });
        });
    }

    private function toEntry(object $row): TimelineEntry
    {
        $isMessage = $row->kind !== TimelineEntry::TICKET_OPENED;

        return new TimelineEntry(
            id: (string) $row->id,
            kind: (string) $row->kind,
            ticketId: (string) $row->ticket_id,
            ticketRef: (string) $row->ticket_ref,
            occurredAt: $this->iso8601((string) $row->occurred_at),
            /*
             * Truncated HERE, not in the browser. Sending whole message bodies
             * to render one line each is the difference between a page that
             * loads and one that does not on a customer with a long history.
             */
            preview: $isMessage && $row->preview !== null
                ? TicketMessage::preview((string) $row->preview)
                : null,
        );
    }

    /**
     * UTC with a `Z`, whatever the driver handed back.
     *
     * Postgres and SQLite format timestamps differently; the client must not
     * have to know which one answered.
     */
    private function iso8601(string $raw): string
    {
        return \Illuminate\Support\Carbon::parse($raw)->toIso8601ZuluString();
    }
}
