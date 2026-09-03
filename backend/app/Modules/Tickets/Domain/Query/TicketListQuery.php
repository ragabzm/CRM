<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Query;

use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The ticket list, filtered and searched.
 *
 * Two implementations of one behaviour, the same split `CustomerSearch` makes:
 *
 *   Postgres uses the generated `search_vector` for free text and pg_trgm for a
 *   reference an agent half-remembers. Both are indexed; that is what makes the
 *   list answer in under a second at fifty thousand rows.
 *
 *   Every other driver falls back to LIKE containment. The test suite runs on
 *   SQLite, so the fallback is what most tests exercise — which is exactly why
 *   TicketSearchPostgresTest exists and runs against a real Postgres when one
 *   is reachable. A search only ever tested on the driver production does not
 *   use is a search nobody has tested.
 *
 * Visibility is applied FIRST and unconditionally. An agent who puts
 * `assignee_id=<a colleague>` in the URL gets their own tickets back, not a
 * refusal — the filter narrows what they may see, it never widens it.
 */
final class TicketListQuery
{
    /**
     * A reference, rather than words.
     *
     * Long enough not to catch a one-word subject; loose enough to match a
     * fragment read off a previous email.
     */
    private const REFERENCE_LIKE = '/^[A-Za-z0-9\-]{3,}$/';

    public function paginate(TicketListFilters $filters, ?Authenticatable $actor): LengthAwarePaginator
    {
        $query = Ticket::query();

        if ($actor !== null) {
            // Before any caller-supplied filter, so nothing below can widen it.
            TicketVisibility::scopeForActor($query, $actor);
        }

        $this->applyFilters($query, $filters);
        $this->applySearch($query, $filters);
        $this->applyOrder($query, $filters);

        return $query->paginate(min($filters->perPage, TicketListFilters::MAX_PER_PAGE));
    }

    /** @param  Builder<Ticket>  $query */
    private function applyFilters(Builder $query, TicketListFilters $filters): void
    {
        if ($filters->status !== []) {
            $query->whereIn('status', $filters->status);
        }

        if ($filters->priority !== []) {
            $query->whereIn('priority', $filters->priority);
        }

        if ($filters->categoryIds !== []) {
            $query->whereIn('category_id', $filters->categoryIds);
        }

        if ($filters->departmentIds !== []) {
            $query->whereIn('department_id', $filters->departmentIds);
        }

        if ($filters->assigneeIds !== []) {
            $named = $filters->namedAssignees();

            /*
             * "Unassigned" is a real answer, not the absence of one — it is the
             * pool an agent picks their next ticket from. Wrapped so the OR
             * cannot escape and widen the visibility scope applied above.
             */
            $query->where(function (Builder $scoped) use ($filters, $named): void {
                if ($named !== []) {
                    $scoped->whereIn('assignee_id', $named);
                }

                if ($filters->wantsUnassigned()) {
                    $named === []
                        ? $scoped->whereNull('assignee_id')
                        : $scoped->orWhereNull('assignee_id');
                }
            });
        }

        if ($filters->createdFrom !== null) {
            $query->where('created_at', '>=', $filters->createdFrom);
        }

        if ($filters->createdTo !== null) {
            // Inclusive of the whole day. A range typed as two dates means "these
            // two days and everything between", not "up to midnight of the second".
            $query->where('created_at', '<=', $filters->createdTo);
        }
    }

    /** @param  Builder<Ticket>  $query */
    private function applySearch(Builder $query, TicketListFilters $filters): void
    {
        $term = trim((string) $filters->term);

        // Whitespace is not a search. Sending it would scan the table for
        // nothing.
        if ($term === '') {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            $this->applyPortableSearch($query, $term);

            return;
        }

        if (preg_match(self::REFERENCE_LIKE, $term) === 1) {
            /*
             * A reference, so trigram: the useful part of a reference an agent
             * remembers is usually the tail, and no btree can answer a leading
             * wildcard. Ordered by similarity, because the closest match is
             * almost always the one they meant.
             */
            $query->whereRaw('reference % ?', [$term])
                ->orderByRaw('similarity(reference, ?) desc', [$term]);

            return;
        }

        $query->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$term])
            ->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('simple', ?)) desc", [$term]);
    }

    /**
     * LIKE containment, for drivers with no text search.
     *
     * @param  Builder<Ticket>  $query
     */
    private function applyPortableSearch(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $scoped) use ($like): void {
            $scoped->where('reference', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    /** @param  Builder<Ticket>  $query */
    private function applyOrder(Builder $query, TicketListFilters $filters): void
    {
        /*
         * Not applied when searching: relevance already ordered the results,
         * and re-sorting by date would throw away the ranking that made the
         * search useful.
         */
        if (trim((string) $filters->term) !== '' && DB::getDriverName() === 'pgsql') {
            return;
        }

        $query->orderBy($filters->sort, $filters->direction)
            // A stable tie-break, so page 2 does not repeat a row from page 1
            // when several share a timestamp.
            ->orderBy('id');
    }
}
