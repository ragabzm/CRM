<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Personal\Task;
use App\Modules\Tickets\Domain\Ticket;
use Carbon\CarbonImmutable;

/**
 * Writes down the thing that must not be forgotten.
 *
 * The owner is the CREATOR and is not a parameter. A task that could be
 * created for somebody else is an assignment, and assignment already exists on
 * the ticket — two ways to give a colleague work is one way too many, and the
 * quiet one is the one nobody watches.
 */
final class CreateTask
{
    public const MAXIMUM_TITLE = 200;

    public function handle(
        int $ownerId,
        string $title,
        ?string $ticketId = null,
        ?CarbonImmutable $dueAt = null,
    ): Task {
        $clean = trim($title);

        if ($clean === '') {
            throw ProblemException::make(
                'tickets.task_title_empty',
                'Give the task a name',
                422,
                'A task needs a title — it is the only thing that says what it is.',
            );
        }

        if ($ticketId !== null && ! Ticket::query()->whereKey($ticketId)->exists()) {
            throw ProblemException::make(
                'tickets.not_found',
                'Ticket not found',
                404,
                "No ticket with id [{$ticketId}].",
            );
        }

        $task = new Task;

        $task->forceFill([
            'user_id' => $ownerId,
            'title' => mb_substr($clean, 0, self::MAXIMUM_TITLE),
            'ticket_id' => $ticketId,
            /*
             * A due date in the past is allowed, unlike a reminder.
             *
             * They are different things: a reminder is a promise to interrupt
             * somebody at a future moment, and one set for yesterday cannot be
             * kept. A due date is a fact about when something was needed by —
             * writing down on Tuesday that a call was due on Monday is exactly
             * how an overdue task gets recorded.
             */
            'due_at' => $dueAt,
            'completed_at' => null,
        ])->save();

        return $task;
    }
}
