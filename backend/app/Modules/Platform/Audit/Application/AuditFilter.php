<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Application;

use App\Modules\Platform\Audit\Domain\AuditAction;
use Illuminate\Support\Carbon;

/**
 * The complete filter surface: actor, action, and a date range.
 *
 * Three facets, deliberately. The temptation with an audit log is to make
 * everything filterable, which produces a query builder nobody can index for
 * and a screen nobody can read. These three answer the questions actually
 * asked after an incident — who, what, and when — and anything else is served
 * by narrowing those and reading.
 *
 * Unknown query parameters are ignored rather than rejected, so a bookmarked
 * URL from a future version still returns rows instead of a validation error.
 */
final class AuditFilter
{
    public function __construct(
        public readonly ?string $actorId = null,
        public readonly ?string $actorSearch = null,
        public readonly ?AuditAction $action = null,
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $action = isset($validated['action']) && is_string($validated['action'])
            ? AuditAction::tryFrom($validated['action'])
            : null;

        return new self(
            actorId: isset($validated['actor_id']) ? (string) $validated['actor_id'] : null,
            actorSearch: isset($validated['actor_search']) ? (string) $validated['actor_search'] : null,
            action: $action,
            /*
             * Inclusive whole days in UTC. `from=2026-09-01&to=2026-09-01`
             * returns that day's entries — a reader who typed the same date
             * twice meant "that day", not "the single instant of midnight".
             */
            from: isset($validated['from']) ? Carbon::parse((string) $validated['from'], 'UTC')->startOfDay() : null,
            to: isset($validated['to']) ? Carbon::parse((string) $validated['to'], 'UTC')->endOfDay() : null,
        );
    }
}
