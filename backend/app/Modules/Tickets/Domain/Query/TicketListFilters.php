<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Query;

/**
 * What the agent asked the list for.
 *
 * Immutable and built once by the form request, so the controller and the query
 * object cannot disagree about what was requested — and so nothing downstream
 * can quietly widen a filter after validation has run.
 */
final readonly class TicketListFilters
{
    /** The sentinel for "nobody has picked this up". */
    public const UNASSIGNED = 'unassigned';

    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /**
     * Sorting is a whitelist, not a passthrough.
     *
     * A caller-supplied ORDER BY column is an injection surface, and an
     * unindexed one is a table scan on every page.
     */
    public const SORTABLE = ['updated_at', 'created_at', 'priority', 'reference', 'status'];

    /**
     * @param  list<string>  $status
     * @param  list<string>  $priority
     * @param  list<int>  $categoryIds
     * @param  list<int|string>  $assigneeIds  Ints, or the `unassigned` sentinel.
     * @param  list<int>  $departmentIds
     */
    public function __construct(
        public array $status = [],
        public array $priority = [],
        public array $categoryIds = [],
        public array $assigneeIds = [],
        public array $departmentIds = [],
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public ?string $term = null,
        public string $sort = 'updated_at',
        public string $direction = 'desc',
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    public function wantsUnassigned(): bool
    {
        return in_array(self::UNASSIGNED, $this->assigneeIds, true);
    }

    /** @return list<int> */
    public function namedAssignees(): array
    {
        return array_values(array_map(
            static fn (int|string $id): int => (int) $id,
            array_filter($this->assigneeIds, static fn (int|string $id): bool => $id !== self::UNASSIGNED),
        ));
    }
}
