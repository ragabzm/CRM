<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Audit;

use Illuminate\Support\Facades\Log;

/**
 * The default binding until Story 2.4 lands the real writer.
 *
 * Writes to the `audit` log channel rather than discarding: a story that ships
 * "auditing" which silently drops every entry is worse than one that admits it
 * has none. This at least leaves a trail an operator can grep, and it exercises
 * the call sites so 2.4 inherits working wiring.
 *
 * TODO(Story 2.4): replace this binding with the persistent audit writer.
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
        string $target,
        array $before,
        array $after,
    ): void {
        Log::channel('audit')->info($action, [
            'actor_id' => $actorUserId,
            'target' => $target,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
