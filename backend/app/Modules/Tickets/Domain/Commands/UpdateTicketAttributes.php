<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Concurrency\VersionGuard;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
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
    use AppendsEvents;

    /** Which event names which attribute change. */
    private const EVENT_FOR = [
        'status' => TicketEvent::STATUS_CHANGED,
        'priority' => TicketEvent::PRIORITY_CHANGED,
        'category_id' => TicketEvent::CATEGORY_CHANGED,
        'assignee_id' => TicketEvent::ASSIGNEE_CHANGED,
        'department_id' => TicketEvent::DEPARTMENT_CHANGED,
    ];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly VersionGuard $guard,
    ) {}

    /**
     * @param  string|null  $overrideEvent  Names the change more specifically —
     *                                      `ticket.resolved` rather than a
     *                                      generic status change.
     * @param  array<string, mixed>  $extraPayload
     */
    public function handle(
        Actor $actor,
        string $ticketId,
        ?int $submittedVersion,
        TicketAttributeChanges $changes,
        ?string $overrideEvent = null,
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
            $this->guard->apply($ticket, $submittedVersion, $columns);

            if (array_key_exists('status', $columns)) {
                $ticket->status->assertCanMoveTo(TicketStatus::from((string) $columns['status']));
            }

            $before = VersionGuard::contendedValues($ticket);

            $ticket->forceFill([...$columns, 'version' => $ticket->version + 1])->save();

            /*
             * One event per changed attribute, unless the caller named the
             * change. "Priority changed" and "assignee changed" are two facts a
             * reader filters on separately; collapsing them into one row would
             * make the history unfilterable.
             */
            $after = VersionGuard::contendedValues($ticket);

            if ($overrideEvent !== null) {
                $this->appendEvent($ticket, $actor, $overrideEvent, [
                    'before' => $before,
                    'after' => $after,
                    ...$extraPayload,
                ]);

                return $ticket;
            }

            foreach (array_keys($columns) as $attribute) {
                $this->appendEvent($ticket, $actor, self::EVENT_FOR[$attribute] ?? 'ticket.changed', [
                    'attribute' => $attribute,
                    'from' => $before[$attribute] ?? null,
                    'to' => $after[$attribute] ?? null,
                ]);
            }

            return $ticket;
        });
    }
}
