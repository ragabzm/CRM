<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reconstructs what the SLA clock needs, from what already happened.
 *
 * Nothing is denormalised onto the ticket. A `first_agent_reply_at` column and
 * a `paused_minutes` column would both be derived values that can drift from
 * the events they summarise — and when they drift, the SLA numbers become
 * wrong in a way nobody can see, because the evidence and the summary no longer
 * agree.
 *
 * The first agent reply is the first outbound message. The pauses are read off
 * the ticket's own history, which records every status change with its before
 * and after. Both are facts already stored for other reasons.
 *
 * Batched by design: reading a ticket list means loading this for twenty-five
 * tickets, and a per-ticket query would be seventy-five round trips for one
 * screen.
 */
final class TicketTimelineLoader
{
    /** The status that means "waiting on the customer". */
    private const PAUSED_STATUS = 'pending';

    /**
     * @param  list<string>  $ticketIds
     * @return array<string, TicketTimeline>  keyed by ticket id
     */
    public function forTickets(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $tickets = DB::table('tickets')
            ->whereIn('id', $ticketIds)
            ->get(['id', 'priority', 'status', 'created_at', 'resolved_at']);

        $firstReplies = $this->firstAgentReplies($ticketIds);
        $pauses = $this->pausedIntervals($ticketIds);

        $timelines = [];

        foreach ($tickets as $ticket) {
            $id = (string) $ticket->id;

            $timelines[$id] = new TicketTimeline(
                ticketId: $id,
                priority: (string) $ticket->priority,
                status: (string) $ticket->status,
                createdAt: CarbonImmutable::parse((string) $ticket->created_at, 'UTC'),
                firstAgentReplyAt: $firstReplies[$id] ?? null,
                resolvedAt: $ticket->resolved_at === null
                    ? null
                    : CarbonImmutable::parse((string) $ticket->resolved_at, 'UTC'),
                pausedIntervals: $pauses[$id] ?? [],
            );
        }

        return $timelines;
    }

    public function forTicket(string $ticketId): ?TicketTimeline
    {
        return $this->forTickets([$ticketId])[$ticketId] ?? null;
    }

    /**
     * When somebody first replied to the customer, per ticket.
     *
     * Outbound only. An internal note is not a reply — the customer never saw
     * it — and counting one would let a desk meet every response target by
     * talking to itself.
     *
     * @param  list<string>  $ticketIds
     * @return array<string, CarbonImmutable>
     */
    private function firstAgentReplies(array $ticketIds): array
    {
        $rows = DB::table('ticket_messages')
            ->whereIn('ticket_id', $ticketIds)
            ->where('direction', 'outbound')
            ->selectRaw('ticket_id, min(sent_at) as first_reply')
            ->groupBy('ticket_id')
            ->get();

        $replies = [];

        foreach ($rows as $row) {
            if ($row->first_reply === null) {
                continue;
            }

            $replies[(string) $row->ticket_id] = CarbonImmutable::parse((string) $row->first_reply, 'UTC');
        }

        return $replies;
    }

    /**
     * The periods each ticket spent waiting on the customer.
     *
     * Replayed from the ticket's own history rather than summed into a column.
     * The history is append-only and cannot be edited, which makes it the one
     * source that cannot silently disagree with itself.
     *
     * @param  list<string>  $ticketIds
     * @return array<string, list<array{from: CarbonImmutable, to: CarbonImmutable|null}>>
     */
    private function pausedIntervals(array $ticketIds): array
    {
        $events = DB::table('ticket_events')
            ->whereIn('ticket_id', $ticketIds)
            ->whereIn('event_type', ['ticket.status_changed', 'ticket.resolved', 'ticket.reopened'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['ticket_id', 'payload', 'created_at']);

        $intervals = [];
        $openFrom = [];

        foreach ($events as $event) {
            $id = (string) $event->ticket_id;
            $payload = json_decode((string) $event->payload, true);
            $after = $payload['after']['status'] ?? null;

            if ($after === null) {
                continue;
            }

            $at = CarbonImmutable::parse((string) $event->created_at, 'UTC');

            if ($after === self::PAUSED_STATUS) {
                // A second pause without an intervening resume would be a
                // history that contradicts itself; keep the first.
                $openFrom[$id] ??= $at;

                continue;
            }

            if (isset($openFrom[$id])) {
                $intervals[$id][] = ['from' => $openFrom[$id], 'to' => $at];
                unset($openFrom[$id]);
            }
        }

        // Whatever is still paused now.
        foreach ($openFrom as $id => $from) {
            $intervals[$id][] = ['from' => $from, 'to' => null];
        }

        return $intervals;
    }
}
