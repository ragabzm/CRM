<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Audit;

use Illuminate\Support\Facades\Log;

/**
 * Writes to the `audit` log channel instead of the table.
 *
 * No longer the default binding — AuditWriter is. This is kept for the two
 * cases that still want it: a test exercising a code path without asserting on
 * audit rows, and any future context where the table is unreachable but losing
 * the trail entirely would be worse than writing it to a file.
 */
final class NullAuditLogger implements AuditLogger
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
    ): void {
        Log::channel('audit')->info($action, [
            'actor_id' => $actorUserId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
