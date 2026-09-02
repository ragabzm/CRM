<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Http\AssigneeDirectory;

/**
 * Hands a ticket to someone, or back to the pool.
 *
 * The interesting rule is not "who may assign" — most staff may — but "whose
 * work may you take". Picking up something unclaimed is what an agent does all
 * day; pulling a ticket out of a colleague's hands mid-conversation is a
 * supervisor's call, because the person holding it may be three messages into
 * a thread nobody else can see.
 */
final class AssignTicket
{
    public function __construct(
        private readonly UpdateTicketAttributes $update,
        private readonly AssigneeDirectory $assignees,
    ) {}

    /**
     * @param  int|null  $assigneeId  Null returns the ticket to the pool.
     * @param  bool  $mayReassignAnyone  Whether the actor holds ticket.reassign_any.
     */
    public function handle(
        Actor $actor,
        string $ticketId,
        ?int $submittedVersion,
        ?int $assigneeId,
        bool $mayReassignAnyone = true,
    ): Ticket {
        $ticket = Ticket::query()->find($ticketId);

        if ($ticket === null) {
            throw ProblemException::make(
                'tickets.not_found',
                'Ticket not found',
                404,
                "No ticket with id [{$ticketId}].",
            );
        }

        $this->assertMayTakeItFrom($ticket, $actor, $mayReassignAnyone);

        if ($assigneeId !== null) {
            $this->assertAssignable($assigneeId, $ticket);
        }

        return $this->update->handle(
            $actor,
            $ticketId,
            $submittedVersion,
            TicketAttributeChanges::of(['assignee_id' => $assigneeId]),
        );
    }

    private function assertMayTakeItFrom(Ticket $ticket, Actor $actor, bool $mayReassignAnyone): void
    {
        $current = $ticket->assignee_id;

        // Unclaimed work is free to pick up.
        if ($current === null || $mayReassignAnyone) {
            return;
        }

        // Handing back what you are already holding is not a reassignment.
        if ((string) $current === (string) $actor->id()) {
            return;
        }

        throw ProblemException::make(
            'tickets.reassign_forbidden',
            'Someone else is working on this',
            403,
            'This ticket is assigned to a colleague. A supervisor can move it — they may be part-way through a conversation you cannot see.',
            ['ticket_id' => (string) $ticket->getKey(), 'current_assignee_id' => $current],
        );
    }

    private function assertAssignable(int $assigneeId, Ticket $ticket): void
    {
        if (! $this->assignees->isAssignable($assigneeId)) {
            throw ProblemException::make(
                'tickets.assignee_invalid',
                'That person cannot take this ticket',
                422,
                'That account is deactivated, so the ticket would sit somewhere nobody is looking.',
                ['assignee_id' => $assigneeId],
            );
        }

        $department = $ticket->department_id === null ? null : (int) $ticket->department_id;

        if (! $this->assignees->belongsToDepartment($assigneeId, $department)) {
            throw ProblemException::make(
                'tickets.assignee_invalid',
                'That person is in a different department',
                422,
                'Move the ticket to their department first, or choose someone from this one.',
                ['assignee_id' => $assigneeId, 'department_id' => $department],
            );
        }
    }
}
