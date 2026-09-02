<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

/**
 * What was asked for. One free-text term, plus two narrowing filters.
 */
final class CustomerSearchCriteria
{
    public function __construct(
        public readonly ?string $term = null,
        public readonly ?CustomerState $state = null,
        public readonly bool $includeAllStates = false,
        public readonly ?int $departmentId = null,
        public readonly int $limit = CustomerSearch::DEFAULT_LIMIT,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $requested = isset($validated['state']) ? (string) $validated['state'] : 'active';

        return new self(
            term: isset($validated['q']) ? trim((string) $validated['q']) : null,
            // "all" is the absence of a state filter, not a third state.
            state: $requested === 'all' ? null : CustomerState::tryFrom($requested),
            includeAllStates: $requested === 'all',
            departmentId: isset($validated['department_id']) ? (int) $validated['department_id'] : null,
            limit: isset($validated['limit']) ? (int) $validated['limit'] : CustomerSearch::DEFAULT_LIMIT,
        );
    }
}
