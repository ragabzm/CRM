<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Audit;

/**
 * Records that something changed, and what it changed from.
 *
 * A seam, not an implementation. Story 2.4 binds the real writer; until then
 * NullAuditLogger satisfies it. The point of introducing the interface now is
 * that every call site is written against the final shape, so 2.4 changes one
 * binding rather than hunting for the places that should have logged.
 *
 * `before` and `after` are both required: an audit entry that records only the
 * new value cannot answer "what did this used to be?", which is the question
 * an incident review actually asks.
 *
 * The target is a TYPE and an ID rather than one string, so the log can answer
 * "everything that happened to this record" without pattern-matching a label
 * whose format nobody agreed on.
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function write(
        ?int $actorUserId,
        string $action,
        string $targetType,
        string $targetId,
        array $before,
        array $after,
    ): void;
}
