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
     * The SLA readings a list can be narrowed to.
     *
     * Not a database column — SLA state is computed from each ticket's
     * timeline, so this filter is answered by asking the Sla module which
     * tickets are in the state and constraining on the ids it names. That is
     * why the set is a whitelist here rather than free text: every value costs
     * a walk over the live queue.
     */
    public const SLA_STATES = ['on_track', 'at_risk', 'breached', 'met', 'paused'];

    /**
     * What the customer said, as a filter.
     *
     * Three values and not four: `rated` is everyone who answered, which is
     * the denominator every satisfaction figure is a proportion of. There is
     * deliberately no `unrated` — an unrated ticket is the absence of an
     * answer, not a verdict, and offering it as a filter beside the other two
     * would invite it into a figure as a third outcome.
     *
     * @var list<string>
     */
    public const SATISFACTION = ['positive', 'negative', 'rated'];

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
        /**
         * Branches the reader chose to look at.
         *
         * A FILTER, never a scope. It narrows a query the caller already had
         * the right to run, exactly like priority or category — and a request
         * that returns a ticket outside the reader's own branch is correct
         * behaviour rather than a leak, because branch is a label and there is
         * no branch-scoped access in this product.
         *
         * @var list<int>
         */
        public array $branchIds = [],
        public ?string $slaState = null,
        /**
         * True narrows to escalated tickets; null means "do not care".
         *
         * Nullable rather than a boolean defaulting to false, because
         * "everything" and "not escalated" are different questions and a
         * boolean can only ask one of them.
         */
        public ?bool $escalated = null,
        /**
         * `positive` · `negative` · `rated`, or null for "do not care".
         *
         * Exists so that every figure on the reports surface opens the tickets
         * behind it. A satisfaction number with no click-through is a number
         * nobody can audit, and this story does not ship those.
         */
        public ?string $satisfaction = null,
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
