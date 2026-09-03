<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Query;

use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The five numbers an agent sees before they see anything else.
 *
 * ONE query, not five. Five round trips on a page that refreshes every thirty
 * seconds is five times the load for a strip of numbers, and the five would be
 * taken at five slightly different moments — so they could disagree with each
 * other and with the list they link to.
 *
 * Every count is scoped by the same visibility rule as the list. A supervisor's
 * "unassigned" is the whole queue; an agent's is the pool they may pick from.
 * A count that included work they cannot open would be a number they can never
 * act on.
 */
final class TicketCounts
{
    /**
     * @return array{
     *     assigned_to_me: int,
     *     unassigned: int,
     *     at_risk: int|null,
     *     breached: int|null,
     *     pending_customer_reply: int
     * }
     */
    public function forActor(?Authenticatable $actor): array
    {
        $query = Ticket::query();

        if ($actor !== null) {
            TicketVisibility::scopeForActor($query, $actor);
        }

        $live = array_map(
            static fn (TicketStatus $s): string => $s->value,
            TicketStatus::openStates(),
        );

        $me = $actor?->getAuthIdentifier();

        /*
         * Conditional aggregates in one pass. `case when … then 1 end` rather
         * than `filter (where …)` so the same statement runs on SQLite, which
         * the test suite uses — a counts query that only works on Postgres is a
         * counts query no test covers.
         */
        $row = $query
            ->selectRaw('count(case when assignee_id = ? and status in (?, ?) then 1 end) as assigned_to_me', [$me, ...$live])
            ->selectRaw('count(case when assignee_id is null and status in (?, ?) then 1 end) as unassigned', $live)
            ->selectRaw('count(case when status = ? then 1 end) as pending_customer_reply', [TicketStatus::Pending->value])
            ->first();

        return [
            'assigned_to_me' => (int) ($row?->assigned_to_me ?? 0),
            'unassigned' => (int) ($row?->unassigned ?? 0),
            'pending_customer_reply' => (int) ($row?->pending_customer_reply ?? 0),

            /*
             * NULL, not 0.
             *
             * The SLA module (Story 5.3) does not exist yet, so there is no
             * `sla_state` column to count. Returning 0 would be a claim — "no
             * ticket is at risk" — that this system is in no position to make,
             * and an agent who read it would stop looking. Null says "not
             * known", and the strip renders it as a dash.
             *
             * The keys are present so the shape does not change when 5.3 lands.
             */
            'at_risk' => null,
            'breached' => null,
        ];
    }
}
