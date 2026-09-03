<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Audit\AuditLogger;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Concurrency\VersionGuard;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Lifecycle\TicketLifecycle;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Database\ConnectionInterface;

/**
 * Changes any subset of a ticket's contended attributes, atomically.
 *
 * The composite command every other mutation is expressed in terms of, so the
 * locking, the version guard, the version bump and the event append exist once
 * rather than five times with four subtle differences.
 */
final class UpdateTicketAttributes
{
    /** Which event names which attribute change. */
    private const EVENT_FOR = [
        'status' => TicketEventKind::StatusChanged,
        'priority' => TicketEventKind::PriorityChanged,
        'category_id' => TicketEventKind::CategoryChanged,
        'assignee_id' => TicketEventKind::AssigneeChanged,
        'department_id' => TicketEventKind::DepartmentChanged,
    ];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly VersionGuard $guard,
        /*
         * The system-wide audit log, which is NOT the ticket's own history.
         * The two coexist deliberately: `ticket_events` is what an agent reads
         * inside the ticket, while the audit log answers "what did this person
         * change across the whole system" for a security review. Merging them
         * would put every sign-in attempt in a customer's ticket history.
         *
         * Tickets (T3) depending on Platform (T0) runs downward, so this is a
         * legal dependency.
         */
        private readonly AuditLogger $audit,
        private readonly TicketLifecycle $lifecycle,
        private readonly TicketEventRecorder $history,
    ) {}

    /**
     * @param  TicketEventKind|null  $overrideEvent  Names the change more
     *                                      specifically — `ticket.resolved`
     *                                      rather than a generic status change.
     * @param  array<string, mixed>  $extraPayload
     */
    public function handle(
        Actor $actor,
        string $ticketId,
        ?int $submittedVersion,
        TicketAttributeChanges $changes,
        ?TicketEventKind $overrideEvent = null,
        array $extraPayload = [],
    ): Ticket {
        if ($changes->isEmpty()) {
            throw ProblemException::make(
                'tickets.no_changes',
                'Nothing to change',
                422,
                'Supply at least one attribute to change.',
            );
        }

        return $this->db->transaction(function () use (
            $actor, $ticketId, $submittedVersion, $changes, $overrideEvent, $extraPayload
        ): Ticket {
            /*
             * Locked for the whole transaction. Without the lock two requests
             * both read version 3, both pass the guard, and both write — which
             * is the exact race the version column exists to stop.
             */
            $ticket = Ticket::query()->whereKey($ticketId)->lockForUpdate()->first();

            if ($ticket === null) {
                throw ProblemException::make(
                    'tickets.not_found',
                    'Ticket not found',
                    404,
                    "No ticket with id [{$ticketId}].",
                );
            }

            $columns = $changes->toColumns();

            // Before the write, and before the lifecycle check: being told the
            // ticket moved on is more useful than being told a transition is
            // illegal when it is only illegal against stale state.
            $this->guard->apply(
                $ticket,
                $submittedVersion,
                $columns,
                // The sweep and the reply listener act on the ticket's own
                // state, not on a screen somebody read.
                exempt: $actor->kind() === 'system',
            );

            $stamps = [];

            if (array_key_exists('status', $columns)) {
                $target = TicketStatus::from((string) $columns['status']);

                $ticket->status->assertCanMoveTo($target);
                $this->lifecycle->assertMayLeaveClosed($ticket, $target, $actor);

                // The timestamps the lifecycle turns on, derived from the
                // transition rather than passed in — a caller could otherwise
                // resolve a ticket without a resolved_at and break the sweep.
                $stamps = $this->lifecycle->stampsFor($ticket, $target);
            }

            $before = VersionGuard::contendedValues($ticket);

            $ticket->forceFill([...$columns, ...($stamps ?? []), 'version' => $ticket->version + 1])->save();

            /*
             * One event per changed attribute, unless the caller named the
             * change. "Priority changed" and "assignee changed" are two facts a
             * reader filters on separately; collapsing them into one row would
             * make the history unfilterable.
             */
            $after = VersionGuard::contendedValues($ticket);

            /*
             * Changed fields only, never the whole row. Storing every contended
             * value on every change would make the reader diff two blobs in
             * their head to find the one value that moved — and would copy data
             * that had nothing to do with the change into a store that can
             * never be corrected.
             */
            $diff = TicketEventRecorder::diffChangedFields(
                $before,
                $after,
                array_keys(VersionGuard::contendedValues($ticket)),
            );

            if ($overrideEvent !== null) {
                $this->history->record(
                    (string) $ticket->getKey(),
                    $overrideEvent,
                    $actor,
                    $diff['before'],
                    $diff['after'],
                    $extraPayload === [] ? null : $extraPayload,
                    $ticket->version,
                );

                foreach (array_keys($columns) as $attribute) {
                    $this->auditFieldChange($actor, $ticket, $attribute, $before, $after);
                }

                return $ticket;
            }

            foreach (array_keys($columns) as $attribute) {
                /*
                 * One event per changed attribute. "Priority changed" and
                 * "assignee changed" are two facts a reader filters on
                 * separately; collapsing them into one row makes the history
                 * unfilterable.
                 *
                 * Skipped when the value did not actually move: a no-op event
                 * is noise in a store nobody can prune.
                 */
                if (! array_key_exists($attribute, $diff['after'])) {
                    continue;
                }

                $this->history->record(
                    (string) $ticket->getKey(),
                    self::EVENT_FOR[$attribute] ?? TicketEventKind::StatusChanged,
                    $actor,
                    [$attribute => $diff['before'][$attribute] ?? null],
                    [$attribute => $diff['after'][$attribute] ?? null],
                    versionAfter: $ticket->version,
                );

                $this->auditFieldChange($actor, $ticket, $attribute, $before, $after);
            }

            return $ticket;
        });
    }

    /**
     * One audit entry per changed field, inside the same transaction.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function auditFieldChange(
        Actor $actor,
        Ticket $ticket,
        string $attribute,
        array $before,
        array $after,
    ): void {
        $this->audit->write(
            // The audit log records numeric staff ids; a portal or system actor
            // has none, and null is the honest answer rather than a zero.
            is_numeric($actor->id()) ? (int) $actor->id() : null,
            AuditAction::TicketFieldChanged->value,
            'ticket',
            (string) $ticket->getKey(),
            [$attribute => $before[$attribute] ?? null],
            [$attribute => $after[$attribute] ?? null],
        );
    }
}
