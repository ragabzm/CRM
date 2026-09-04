<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Query;

use App\Modules\Tickets\Contracts\SlaReader;
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
    /*
     * Through the CONTRACT, not the Sla module. Sla is T4 and this is T3, so
     * the dependency has to point downward — and a deployment with the engine
     * switched off binds the null implementation, which answers "not known"
     * without this class knowing there is anything to switch off.
     */
    public function __construct(private readonly SlaReader $sla) {}

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

        /*
         * The SLA tallies are a second query, and they have to be.
         *
         * SLA state is COMPUTED from each ticket's timeline, not stored — a
         * stored state is wrong the second after it is written — so there is
         * no column to put in the aggregate above. What bounds the cost is
         * that only live tickets are ever asked about: a desk's open queue,
         * not its history.
         *
         * These returned a hard `null` for three stories with a comment saying
         * the SLA module did not exist yet. It shipped, the comment stayed,
         * and the two tiles kept reading "Not tracked yet" on a screen whose
         * list column was showing live SLA badges two inches below them.
         */
        $sla = $this->sla->countsAmong($this->liveTicketIds($actor));

        return [
            'assigned_to_me' => (int) ($row?->assigned_to_me ?? 0),
            'unassigned' => (int) ($row?->unassigned ?? 0),
            'pending_customer_reply' => (int) ($row?->pending_customer_reply ?? 0),

            /*
             * Still NULL when nothing is tracking. "We do not know" and "none"
             * are different answers, and the strip renders the first as a dash
             * with a line saying why.
             */
            'at_risk' => $sla['at_risk'],
            'breached' => $sla['breached'],
        ];
    }

    /**
     * The live tickets this person can see.
     *
     * Ids only, and only the open ones: this is the set the SLA reader will
     * compute a timeline for, so it is the set whose size decides what this
     * costs.
     *
     * @return list<string>
     */
    private function liveTicketIds(?Authenticatable $actor): array
    {
        $query = Ticket::query();

        if ($actor !== null) {
            TicketVisibility::scopeForActor($query, $actor);
        }

        return $query
            ->whereIn('status', array_map(
                static fn (TicketStatus $s): string => $s->value,
                TicketStatus::openStates(),
            ))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }
}
