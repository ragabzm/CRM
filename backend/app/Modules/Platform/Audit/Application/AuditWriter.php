<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Application;

use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Audit\Domain\AuditActorType;
use App\Modules\Platform\Audit\Domain\AuditRedactor;
use App\Modules\Platform\Support\Audit\AuditLogger;
use App\Modules\Platform\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

/**
 * The only way an audit entry comes into existence.
 *
 * Writes through the query builder rather than the Eloquent model on purpose.
 * The model refuses every mutation, which makes it a poor thing to insert with;
 * more importantly, a model insert runs `creating`/`created` hooks, and a hook
 * added later by someone solving an unrelated problem is a way for the recorded
 * content to differ from what actually happened.
 *
 * Implements `AuditLogger` so the seam Story 2.3 wrote its call sites against
 * keeps working unchanged — that story's controllers do not learn a new API,
 * they just start writing to a table instead of a log file.
 */
final class AuditWriter implements AuditLogger
{
    public function __construct(
        private readonly RequestContext $context,
        private readonly AuditRedactor $redactor,
        private readonly int $maxPayloadBytes,
    ) {}

    /**
     * The narrow seam: a keyed change with a before and an after.
     *
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
        $resolved = AuditAction::tryFrom($action);

        /*
         * An unrecognised action name is recorded rather than dropped. The
         * entry is the point; a caller using a name nobody added to the enum is
         * a naming bug, and losing the evidence of what they did is a much
         * worse one. It surfaces as an unfamiliar row instead of as silence.
         */
        $this->insert(
            action: $resolved?->value ?? $action,
            targetType: $targetType,
            targetId: $targetId,
            before: $before,
            after: $after,
            actorType: $actorUserId !== null ? AuditActorType::User : null,
            actorId: $actorUserId !== null ? (string) $actorUserId : null,
            actorLabel: null,
        );
    }

    /**
     * The full surface.
     *
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>|null  $after
     */
    public function record(
        AuditAction $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $before = null,
        ?array $after = null,
        ?AuditActorType $actorType = null,
        ?string $actorId = null,
        ?string $actorLabel = null,
    ): string {
        return $this->insert(
            action: $action->value,
            targetType: $targetType,
            targetId: $targetId,
            before: $before,
            after: $after,
            actorType: $actorType,
            actorId: $actorId,
            actorLabel: $actorLabel,
        );
    }

    /**
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>|null  $after
     * @return string The id of the entry written.
     */
    private function insert(
        string $action,
        ?string $targetType,
        ?string $targetId,
        ?array $before,
        ?array $after,
        ?AuditActorType $actorType,
        ?string $actorId,
        ?string $actorLabel,
    ): string {
        [$type, $id, $label] = $this->resolveActor($actorType, $actorId, $actorLabel);

        $entryId = (string) Str::ulid();

        DB::table('audit_entries')->insert([
            'id' => $entryId,
            'occurred_at' => now(),
            'actor_id' => $id,
            'actor_type' => $type->value,
            'actor_label' => $label,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before' => $this->encode($this->redactor->redact($before)),
            'after' => $this->encode($this->redactor->redact($after)),
            'source_ip' => $this->context->clientIp(),
            // Ties the entry to the request's log lines and to the trace_id in
            // any problem document the same request produced.
            'request_id' => $this->context->requestId(),
        ]);

        return $entryId;
    }

    /**
     * @return array{0: AuditActorType, 1: string|null, 2: string}
     */
    private function resolveActor(
        ?AuditActorType $actorType,
        ?string $actorId,
        ?string $actorLabel,
    ): array {
        if ($actorType === null) {
            [$contextType, $contextId] = $this->context->actor();
            $actorType = AuditActorType::tryFrom($contextType) ?? AuditActorType::Guest;
            $actorId ??= $contextId;
        }

        /*
         * The label is denormalised and never joined back to `users`. A log
         * that renders "user 41" is unreadable a year later, and one that joins
         * loses the name the moment the account is renamed or removed — which
         * is exactly when someone is reading it.
         */
        $label = $actorLabel
            ?? $this->context->actorLabel()
            ?? ($actorId !== null ? $actorType->value.' '.$actorId : $actorType->value);

        return [$actorType, $actorId, $label];
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     */
    private function encode(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            // Unencodable content must not cost us the entry itself.
            return $this->marker(['_unencodable' => true, 'reason' => $e->getMessage()]);
        }

        if (strlen($json) <= $this->maxPayloadBytes) {
            return $json;
        }

        /*
         * Truncated to a marker rather than to a prefix. A prefix of JSON is
         * not JSON, so it would fail to decode on read and take the whole row's
         * readability with it; the marker at least records that something large
         * happened and how large.
         */
        return $this->marker(['_truncated' => true, 'sizeBytes' => strlen($json)]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function marker(array $fields): string
    {
        return json_encode($fields, JSON_THROW_ON_ERROR);
    }
}
