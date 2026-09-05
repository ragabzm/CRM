<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Personal\Reminder;
use App\Modules\Tickets\Domain\Personal\Task;
use App\Modules\Tickets\Domain\Ticket;
use Carbon\CarbonImmutable;

/**
 * "Interrupt me about this, then."
 *
 * THE PAST-DATE REFUSAL LIVES HERE, not in the date picker. The API is
 * reachable directly, and the two wrong ways to handle a past date are both
 * worse than refusing: firing it immediately turns a mistyped year into an
 * instant notification about nothing, and silently moving it to now hides the
 * mistake so the agent never learns they set it wrong.
 */
final class CreateReminder
{
    public function handle(
        int $ownerId,
        CarbonImmutable $remindAt,
        ?string $ticketId = null,
        ?string $taskId = null,
    ): Reminder {
        if (($ticketId === null) === ($taskId === null)) {
            /*
             * Exactly one target. A reminder about both would appear twice on
             * the Home tab; one about neither has nothing to open when it
             * fires, and its notification could only say "something".
             */
            throw ProblemException::make(
                'tickets.reminder_target_required',
                'Say what the reminder is about',
                422,
                'A reminder is set on a ticket or on a task — one of the two, not both and not neither.',
            );
        }

        if (! $remindAt->isFuture()) {
            throw ProblemException::make(
                'tickets.reminder_in_the_past',
                'That moment has already passed',
                422,
                'Pick a time in the future — a reminder cannot be set for a moment that has gone.',
                ['remind_at' => $remindAt->toIso8601String(), 'now' => now()->toIso8601String()],
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

        if ($taskId !== null) {
            $task = Task::query()->whereKey($taskId)->first();

            // Somebody else's task is not found, for the same reason ticking
            // one is not found: the id is the only thing keeping the two lists
            // apart.
            if ($task === null || (int) $task->user_id !== $ownerId) {
                throw ProblemException::make(
                    'tickets.task_not_found',
                    'Task not found',
                    404,
                    'That task does not exist, or it is not yours.',
                );
            }
        }

        $reminder = new Reminder;

        $reminder->forceFill([
            'user_id' => $ownerId,
            'ticket_id' => $ticketId,
            'task_id' => $taskId,
            'remind_at' => $remindAt,
            'fired_at' => null,
        ])->save();

        return $reminder;
    }
}
