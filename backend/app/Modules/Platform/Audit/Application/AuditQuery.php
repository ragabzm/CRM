<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Application;

use App\Modules\Platform\Audit\Domain\AuditEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The read side. Queries only — there is no write path through here.
 */
final class AuditQuery
{
    public const MAX_PER_PAGE = 100;

    public function paginate(AuditFilter $filter, int $perPage = 25): LengthAwarePaginator
    {
        return $this->apply($filter)
            /*
             * Newest first, then by id. Entries written in the same
             * millisecond would otherwise come back in an order the database
             * is free to change between pages, which silently duplicates and
             * drops rows as the reader pages through. The ULID tiebreak sorts
             * by creation order, so the sequence is total and stable.
             */
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(min($perPage, self::MAX_PER_PAGE));
    }

    public function find(string $id): ?AuditEntry
    {
        return AuditEntry::query()->find($id);
    }

    private function apply(AuditFilter $filter): Builder
    {
        $query = AuditEntry::query();

        if ($filter->actorId !== null) {
            $query->where('actor_id', $filter->actorId);
        }

        if ($filter->actorSearch !== null && $filter->actorSearch !== '') {
            // Case-insensitive on the denormalised label, so searching by the
            // name someone remembers works even after the account was renamed.
            $query->whereRaw('lower(actor_label) like ?', ['%'.mb_strtolower($filter->actorSearch).'%']);
        }

        if ($filter->action !== null) {
            $query->where('action', $filter->action->value);
        }

        if ($filter->from !== null) {
            $query->where('occurred_at', '>=', $filter->from);
        }

        if ($filter->to !== null) {
            $query->where('occurred_at', '<=', $filter->to);
        }

        return $query;
    }
}
