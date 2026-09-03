<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Contracts\DepartmentDirectory;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Http\AssigneeDirectory;

/**
 * Moves a ticket to another department.
 *
 * A named command rather than a generic attribute update, because moving work
 * between teams is a thing people do on purpose and want to find in the
 * history — and because it can orphan the assignee, which a plain field update
 * would do silently.
 */
final class ChangeDepartment
{
    public function __construct(
        private readonly UpdateTicketAttributes $update,
        private readonly DepartmentDirectory $departments,
        private readonly AssigneeDirectory $assignees,
    ) {}

    public function handle(Actor $actor, string $ticketId, ?int $submittedVersion, int $departmentId): Ticket
    {
        if (! $this->departments->exists($departmentId)) {
            throw ProblemException::make(
                'tickets.department_invalid',
                'That department does not exist',
                422,
                'Choose a department from the list.',
                ['department_id' => $departmentId],
            );
        }

        $ticket = $this->update->handle(
            $actor,
            $ticketId,
            $submittedVersion,
            TicketAttributeChanges::of(['department_id' => $departmentId]),
        );

        $this->clearOrphanedAssignee($actor, $ticket, $departmentId);

        return $ticket->refresh();
    }

    /**
     * Drops an assignee the move has stranded.
     *
     * Cleared and RECORDED, rather than refusing the move: the move is what
     * somebody asked for and is usually right, while the assignment is a
     * detail that quietly stopped making sense. Refusing would make them undo
     * the assignment first and then move — two steps for one intention.
     *
     * Its own history entry, so "why is this unassigned?" has an answer.
     */
    private function clearOrphanedAssignee(Actor $actor, Ticket $ticket, int $departmentId): void
    {
        $assigneeId = $ticket->assignee_id;

        if ($assigneeId === null) {
            return;
        }

        if ($this->assignees->belongsToDepartment((int) $assigneeId, $departmentId)) {
            return;
        }

        $this->update->handle(
            $actor,
            (string) $ticket->getKey(),
            // The version just moved; read it rather than making the caller
            // guess, since this is our own follow-up write and not theirs.
            (int) $ticket->refresh()->version,
            TicketAttributeChanges::of(['assignee_id' => null]),
            TicketEventKind::AssigneeChanged,
            [
                'reason' => 'department_changed',
                'detail' => 'Unassigned automatically: the previous assignee is not in the ticket\'s new department.',
                'previous_assignee_id' => $assigneeId,
            ],
        );
    }
}
