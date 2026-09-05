<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Personal\Task;

/**
 * Ticks the box, or unticks it.
 *
 * Completion is a STATE, not a delete. Somebody who ticks the wrong row has to
 * be able to put it back, and a completed task is the only evidence the work
 * happened — deleting it loses both.
 *
 * A task never touches its ticket. Not its status, not its assignee, not its
 * SLA: an agent's private note to self is not a statement about where the
 * customer's request has got to, and wiring the two would let a personal
 * checklist quietly resolve people's tickets.
 */
final class SetTaskCompletion
{
    public function handle(int $ownerId, string $taskId, bool $complete): Task
    {
        $task = Task::query()->whereKey($taskId)->first();

        /*
         * Not found rather than forbidden when it is somebody else's.
         *
         * A 403 would confirm the id exists, and these ids are the only thing
         * separating one agent's private list from another's.
         */
        if ($task === null || (int) $task->user_id !== $ownerId) {
            throw ProblemException::make(
                'tickets.task_not_found',
                'Task not found',
                404,
                'That task does not exist, or it is not yours.',
            );
        }

        // Idempotent: ticking a ticked box keeps the original completion time
        // rather than moving it to now.
        if ($complete === $task->isComplete()) {
            return $task;
        }

        $task->forceFill(['completed_at' => $complete ? now() : null])->save();

        return $task;
    }
}
