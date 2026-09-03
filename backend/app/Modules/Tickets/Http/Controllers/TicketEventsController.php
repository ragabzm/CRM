<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use App\Modules\Tickets\Http\AssigneeDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What happened to this ticket, in order.
 *
 * Read-only by construction: this class has an `index` and nothing else, and
 * `routes/api.php` registers only GET. There is no update method to reach even
 * if a route for one were added by mistake.
 */
final class TicketEventsController extends Controller
{
    /** Enough to read a history without scrolling forever. */
    private const DEFAULT_LIMIT = 50;

    /** A page this size is already an export; beyond it, use one. */
    private const MAX_LIMIT = 200;

    public function __construct(private readonly AssigneeDirectory $people) {}

    /**
     * @response array{data: array<int, array<string, mixed>>, next_cursor: string|null, has_more: bool}
     */
    public function index(Request $request, string $ticket): JsonResponse
    {
        $this->assertVisible($request, $ticket);

        $limit = $this->limit($request);

        $page = TicketEvent::query()
            ->where('ticket_id', $ticket)
            // Oldest first: a history is read as a story, from the beginning.
            // ULIDs tie-break events written in the same transaction, which
            // share a timestamp to the second.
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate($limit, ['*'], 'cursor', $request->query('cursor'));

        $events = $page->items();

        return new JsonResponse([
            'data' => $this->shapeAll($events),
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /**
     * 404 for a ticket the caller may not see — never 403.
     *
     * A 403 would confirm the ticket exists, and an agent could walk ids to
     * learn how many tickets the business has and when they were created.
     */
    private function assertVisible(Request $request, string $ticket): void
    {
        $actor = $request->user();

        $query = Ticket::query()->whereKey($ticket);

        if ($actor !== null) {
            // The one row-level rule, applied at the query site — the same call
            // TicketsController@show makes. Capability alone is not enough:
            // `ticket.read` says an agent may read tickets, not that they may
            // read THIS one.
            TicketVisibility::scopeForActor($query, $actor);
        }

        if (! $query->exists()) {
            throw ProblemException::make(
                'tickets.not_found',
                'Ticket not found',
                404,
                'No ticket matches this identifier.',
            );
        }
    }

    private function limit(Request $request): int
    {
        $requested = (int) $request->query('limit', (string) self::DEFAULT_LIMIT);

        if ($requested < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($requested, self::MAX_LIMIT);
    }

    /**
     * @param  array<int, TicketEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function shapeAll(array $events): array
    {
        /*
         * One lookup for the whole page, not one per row. A long history is
         * mostly the same few people, and a per-row join would turn a 200-event
         * page into 200 queries.
         */
        $names = $this->people->namesFor(
            array_values(array_unique(array_filter(
                array_map(static fn (TicketEvent $e): ?string => $e->actor_type === 'staff'
                    ? (string) $e->actor_id
                    : null, $events),
            )))
        );

        return array_map(fn (TicketEvent $event): array => $this->shape($event, $names), $events);
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function shape(TicketEvent $event, array $names): array
    {
        $payload = $event->payload ?? [];

        return [
            'id' => (string) $event->getKey(),
            'kind' => $event->event_type,
            'actor' => $this->actor($event, $names),
            'before' => $payload['before'] ?? null,
            'after' => $payload['after'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'version_after' => $event->version_after,
            'occurred_at' => $event->created_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function actor(TicketEvent $event, array $names): array
    {
        if ($event->actor_type === 'system') {
            /*
             * No id, ever. A system action carries no person, and inventing one
             * — even the id of whoever happened to trigger the job — would put
             * a name against a decision they did not make.
             */
            return ['type' => 'system', 'reason' => $event->actor_reason];
        }

        $id = $event->actor_id === null ? null : (string) $event->actor_id;

        return [
            'type' => $event->actor_type,
            'id' => $id,
            // Null rather than a placeholder: a deleted account should read as
            // unknown, not as a person who is still there.
            'display_name' => $id === null ? null : ($names[$id] ?? null),
        ];
    }
}
