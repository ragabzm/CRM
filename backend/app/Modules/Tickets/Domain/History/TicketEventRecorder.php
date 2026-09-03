<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\History;

use App\Modules\Tickets\Contracts\TicketEventRecording;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/**
 * The one writer of ticket history.
 *
 * Every append goes through here so the event and the change it describes
 * cannot come apart. A command that wrote the ticket and forgot the event
 * leaves a hole in the history — and the hole is precisely the change someone
 * is later trying to explain.
 *
 * ASSUMPTION, documented because it is not enforced here: the caller already
 * holds the ticket row (VersionGuard has run and the row is locked). This
 * recorder writes what it is told; deciding whether the change was allowed is
 * the command's job.
 */
final class TicketEventRecorder implements TicketEventRecording
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * Appends one event, and refuses to do it outside a transaction.
     *
     * The refusal is the whole mechanism. Nothing stops a future command from
     * writing the ticket, returning, and recording afterwards — except that
     * doing so throws here, loudly, the first time it is run. Without it, a
     * ticket write that succeeded while its event failed would produce exactly
     * the silent gap this store exists to prevent.
     *
     * @param  array<string, mixed>|null  $before  Changed fields only.
     * @param  array<string, mixed>|null  $after   Changed fields only.
     * @param  array<string, mixed>|null  $meta
     */
    /**
     * The cross-module door, in primitives.
     *
     * Narrow on purpose: another module needs to record that something
     * happened, not to construct a Tickets domain object in order to say it.
     * See TicketEventRecording for why it is system-only.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $meta
     */
    public function recordSystemEvent(
        string $ticketId,
        string $kind,
        string $reason,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
    ): void {
        $this->record(
            $ticketId,
            TicketEventKind::from($kind),
            Actor::system($reason),
            $before,
            $after,
            $meta,
        );
    }

    public function record(
        string $ticketId,
        TicketEventKind $kind,
        Actor $actor,
        ?array $before,
        ?array $after,
        ?array $meta = null,
        ?int $versionAfter = null,
    ): void {
        $this->recordAndReturn($ticketId, $kind, $actor, $before, $after, $meta, $versionAfter);
    }

    /**
     * The same append, handing back the row.
     *
     * Separate from `record()` so callers inside Tickets can read what they
     * just wrote without widening the cross-module contract to return a model.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $meta
     */
    public function recordAndReturn(
        string $ticketId,
        TicketEventKind $kind,
        Actor $actor,
        ?array $before,
        ?array $after,
        ?array $meta = null,
        ?int $versionAfter = null,
    ): TicketEvent {
        if ($this->db->transactionLevel() === 0) {
            throw new LogicException(
                'TicketEventRecorder must be called inside a transaction, '
                .'so a ticket write and its history entry succeed or fail together.'
            );
        }

        $event = new TicketEvent;

        $event->forceFill([
            'ticket_id' => $ticketId,
            'event_type' => $kind->value,
            'payload' => $this->payload($before, $after, $meta),
            'actor_type' => $actor->kind(),
            'actor_id' => $actor->id(),
            'actor_reason' => $actor->reason(),
            // The version the ticket held AFTER this event, so replaying the
            // history reproduces the version sequence exactly.
            'version_after' => $versionAfter ?? $this->versionOf($ticketId),
            'created_at' => now(),
        ])->save();

        return $event;
    }

    /**
     * The fields that actually differ, and nothing else.
     *
     * Storing the whole row on every change makes a history that cannot be read
     * — the reader has to diff two blobs in their head to find the one value
     * that moved. It also copies data that had nothing to do with the change
     * into a store that is never allowed to be corrected.
     *
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $updated
     * @param  list<string>  $keys
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public static function diffChangedFields(array $original, array $updated, array $keys): array
    {
        $before = [];
        $after = [];

        foreach ($keys as $key) {
            $was = $original[$key] ?? null;
            $now = $updated[$key] ?? null;

            // Loose-safe comparison: these values arrive as scalars from the
            // model, and `1 !== '1'` would record a change that never happened.
            if ($was == $now && (($was === null) === ($now === null))) {
                continue;
            }

            $before[$key] = $was;
            $after[$key] = $now;
        }

        return ['before' => $before, 'after' => $after];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>
     */
    private function payload(?array $before, ?array $after, ?array $meta): array
    {
        /*
         * One shape for every kind: {before, after, meta}. It used to vary —
         * some events carried `{before, after}` and others `{attribute, from,
         * to}` — which meant the panel reading this store had to know which
         * kind it was looking at before it could find the values. A reader of a
         * history should not have to.
         *
         * Keys are always present, so a consumer never has to distinguish
         * "absent" from "null".
         */
        return [
            'before' => $before,
            'after' => $after,
            'meta' => $meta,
        ];
    }

    private function versionOf(string $ticketId): int
    {
        return (int) (Ticket::query()->whereKey($ticketId)->value('version') ?? 0);
    }
}
