<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finding a customer from whatever the agent has in front of them.
 *
 * That is a partial name, half an email, a phone number written the way the
 * customer said it, or a reference read off a previous ticket — so all four are
 * searched at once rather than behind a "search by" dropdown nobody wants to
 * touch while a caller waits.
 *
 * Two implementations of one behaviour:
 *
 *   Postgres uses pg_trgm similarity, which tolerates typos and word order and
 *   is indexed. That is what production runs.
 *
 *   Every other driver falls back to LIKE containment. The test suite runs on
 *   SQLite, so the fallback is what most tests exercise — which is why
 *   CustomerSearchPostgresTest exists and runs against a real Postgres when one
 *   is reachable. A search that is only ever tested on the driver production
 *   does not use is a search nobody has actually tested.
 */
final class CustomerSearch
{
    /**
     * Below this similarity a "match" is noise.
     *
     * 0.2 is deliberately permissive: an agent with a caller on the line would
     * far rather skim three wrong names than be told "no results" for a name
     * they slightly misheard.
     */
    public const SIMILARITY_THRESHOLD = 0.2;

    /**
     * How many digits a query needs before it is treated as a phone number.
     *
     * Without a floor, searching a reference like "C-3AB" yields the single
     * digit "3" and matches every customer whose number contains a 3 anywhere.
     * Four is short enough for the last block of a number an agent half
     * remembers and long enough to mean something.
     */
    public const PHONE_SEARCH_MIN_DIGITS = 4;

    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    public function paginate(CustomerSearchCriteria $criteria): LengthAwarePaginator
    {
        $query = Customer::query()->with('identifiers');

        $this->applyState($query, $criteria);

        if ($criteria->departmentId !== null) {
            $query->where('department_id', $criteria->departmentId);
        }

        if ($criteria->term === null || $criteria->term === '') {
            // No term: the most recently touched records, which is what an
            // agent returning to work wants to see.
            $query->orderByDesc('updated_at');

            return $query->paginate(min($criteria->limit, self::MAX_LIMIT));
        }

        return $this->usesTrigrams()
            ? $this->rankByTrigram($query, $criteria)
            : $this->filterByContainment($query, $criteria);
    }

    /** The query's digits, or an empty string when there are too few to mean anything. */
    private static function phoneFragment(string $term): string
    {
        $digits = IdentifierNormaliser::normalise(ContactKind::Phone, $term);

        return strlen($digits) >= self::PHONE_SEARCH_MIN_DIGITS ? $digits : '';
    }

    private function applyState(Builder $query, CustomerSearchCriteria $criteria): void
    {
        /*
         * Deactivated customers are absent by default, not gone. Someone who
         * left three years ago should not clutter today's lookup — but a direct
         * link to them still resolves, and asking for them explicitly finds
         * them.
         */
        if ($criteria->state !== null) {
            $query->where('state', $criteria->state->value);

            return;
        }

        if (! $criteria->includeAllStates) {
            $query->where('state', CustomerState::Active->value);
        }
    }

    public function usesTrigrams(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * The production path: rank by the best of four similarities.
     */
    private function rankByTrigram(Builder $query, CustomerSearchCriteria $criteria): LengthAwarePaginator
    {
        $term = $criteria->term ?? '';
        $email = IdentifierNormaliser::normalise(ContactKind::Email, $term);
        $phone = self::phoneFragment($term);

        /*
         * One query with a correlated sub-select for the identifier score, not
         * a join: a customer with four identifiers would otherwise appear four
         * times and have to be de-duplicated in PHP, which breaks both the
         * count and the paging.
         */
        $identifierScore = <<<'SQL'
            coalesce((
                select max(similarity(ci.value_normalised, ?))
                from contact_identifiers ci
                where ci.customer_id = customers.id
            ), 0)
        SQL;

        $rank = "greatest(similarity(customers.full_name, ?), {$identifierScore})";

        $bindings = [$term, $phone !== '' ? $phone : $email];

        $query
            ->select('customers.*')
            ->selectRaw("{$rank} as rank", $bindings)
            ->where(function (Builder $where) use ($rank, $bindings, $term): void {
                $where
                    ->whereRaw("{$rank} >= ?", [...$bindings, self::SIMILARITY_THRESHOLD])
                    // A reference is quoted exactly or not at all — similarity
                    // on an eight-character code produces nonsense matches.
                    ->orWhere('customers.reference', 'ilike', $term.'%');
            })
            ->orderByDesc('rank')
            ->orderByDesc('customers.updated_at');

        return $query->paginate(min($criteria->limit, self::MAX_LIMIT));
    }

    /**
     * The portable path: substring containment, case-insensitively.
     *
     * Less forgiving than trigrams — it will not survive a typo — but it finds
     * every case an exact partial would, which is what the behavioural tests
     * assert. The ranking is by recency because there is no score to rank by.
     */
    private function filterByContainment(Builder $query, CustomerSearchCriteria $criteria): LengthAwarePaginator
    {
        $term = mb_strtolower(trim($criteria->term ?? ''));
        $email = IdentifierNormaliser::normalise(ContactKind::Email, $term);
        $phone = self::phoneFragment($term);

        $query->where(function (Builder $where) use ($term, $email, $phone): void {
            $where
                ->whereRaw('lower(customers.full_name) like ?', ['%'.$term.'%'])
                ->orWhereRaw('lower(customers.reference) like ?', [mb_strtolower($term).'%'])
                ->orWhereHas('identifiers', function (Builder $identifiers) use ($email, $phone): void {
                    $identifiers->where(function (Builder $any) use ($email, $phone): void {
                        if ($email !== '') {
                            $any->orWhere('value_normalised', 'like', '%'.$email.'%');
                        }

                        if ($phone !== '') {
                            $any->orWhere('value_normalised', 'like', '%'.$phone.'%');
                        }
                    });
                });
        });

        $query->orderByDesc('customers.updated_at');

        return $query->paginate(min($criteria->limit, self::MAX_LIMIT));
    }
}
