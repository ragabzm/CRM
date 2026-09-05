<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Priority;
use App\Modules\Tickets\Domain\Ticket;

/**
 * One step up the priority scale, for a sweep that has noticed a missed target.
 *
 * A named command rather than a raw update, because it is a WRITE to a
 * contended attribute and every one of those goes through
 * `UpdateTicketAttributes` — the lock, the history entry and the audit row
 * exist there once rather than here again, differently.
 *
 * It passes no version. The version guard's job is to stop one person silently
 * overwriting another's edit, and a sweep read no screen to be stale against —
 * the same exemption auto-close has carried since Story 4.2, for the same
 * reason. `UpdateTicketAttributes` grants it by actor kind, so this command
 * gets it by being called with a System actor and by nothing else.
 *
 * Urgent is left alone, silently. See `Priority::raisedOneStep`.
 */
final class RaisePriorityOneStep
{
    public function __construct(private readonly UpdateTicketAttributes $update) {}

    /** @return bool True when the priority actually moved. */
    public function handle(Actor $actor, Ticket $ticket): bool
    {
        $raised = $ticket->priority->raisedOneStep();

        if ($raised === $ticket->priority) {
            return false;
        }

        $this->update->handle(
            $actor,
            (string) $ticket->getKey(),
            // No version: see the class note.
            null,
            TicketAttributeChanges::of(['priority' => $raised]),
        );

        return true;
    }
}
