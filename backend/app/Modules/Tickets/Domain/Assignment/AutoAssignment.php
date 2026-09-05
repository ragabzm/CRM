<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Assignment;

use App\Modules\Tickets\Http\AssigneeDirectory;

/**
 * Where a new ticket should land, according to the mapping table.
 *
 * ONE indexed lookup, not an evaluator. There is no condition parser, no
 * expression language and nothing to order: `unique(source_type, source_id)`
 * means a category has at most one mapping and a department has at most one,
 * so "which rule wins" has exactly one answer and it is written below in four
 * lines rather than resolved at runtime.
 *
 * Category beats department, always, and that is not configurable. A
 * department is where a ticket lands by default; a category is something
 * somebody CHOSE about this particular ticket, and the more specific statement
 * should not lose to the general one. The editor says so on screen rather than
 * leaving it to be discovered.
 */
final class AutoAssignment
{
    public function __construct(private readonly AssigneeDirectory $assignees) {}

    /**
     * @return array{assignee_id?: int, department_id?: int, mapping_id: int}|null
     *         Null when nothing matched, or when the only match points at
     *         somebody who cannot take work.
     */
    public function decide(?int $categoryId, ?int $departmentId): ?array
    {
        $sources = array_filter([
            MappingSource::Category->value => $categoryId,
            MappingSource::Department->value => $departmentId,
        ], static fn (?int $id): bool => $id !== null);

        if ($sources === []) {
            return null;
        }

        /*
         * One query for both possible sources, then the precedence applied in
         * PHP. Two queries would be two round trips on the busiest write path
         * in the product, and an `ORDER BY CASE` would hide the precedence in
         * SQL where nobody reading the rule would look for it.
         */
        $rows = AssignmentMapping::query()
            ->where(function ($query) use ($sources): void {
                foreach ($sources as $type => $id) {
                    $query->orWhere(function ($clause) use ($type, $id): void {
                        $clause->where('source_type', $type)->where('source_id', $id);
                    });
                }
            })
            ->get()
            ->keyBy(static fn (AssignmentMapping $m): string => $m->source_type->value);

        foreach (MappingSource::inPrecedenceOrder() as $source) {
            $mapping = $rows->get($source->value);

            if ($mapping === null) {
                continue;
            }

            return $this->resolve($mapping);
        }

        return null;
    }

    /**
     * @return array{assignee_id?: int, department_id?: int, mapping_id: int}|null
     */
    private function resolve(AssignmentMapping $mapping): ?array
    {
        if ($mapping->target_type === MappingTarget::Department) {
            return ['department_id' => $mapping->target_id, 'mapping_id' => (int) $mapping->getKey()];
        }

        /*
         * A deactivated agent means UNASSIGNED, not somebody else.
         *
         * There is no fallback agent, no next-in-list and no handing it to a
         * supervisor. A ticket in the unassigned pool is visible, counted and
         * workable — whoever picks up work next will see it. A ticket forced
         * onto a colleague who did not expect it, or onto somebody who left
         * last month, is worse in every way and nothing reports it.
         */
        if (! $this->assignees->isAssignable($mapping->target_id)) {
            return null;
        }

        return ['assignee_id' => $mapping->target_id, 'mapping_id' => (int) $mapping->getKey()];
    }
}
