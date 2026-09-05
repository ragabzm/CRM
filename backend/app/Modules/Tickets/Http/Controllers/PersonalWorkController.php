<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Personal\Commands\CreateReminder;
use App\Modules\Tickets\Domain\Personal\Commands\CreateTask;
use App\Modules\Tickets\Domain\Personal\Commands\SetTaskCompletion;
use App\Modules\Tickets\Domain\Personal\NoteMention;
use App\Modules\Tickets\Domain\Personal\Reminder;
use App\Modules\Tickets\Domain\Personal\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * An agent's own tasks, reminders and mentions.
 *
 * `/me/…`, and every method reads the owner off the authenticated user rather
 * than from the request. There is no way to ask for somebody else's list —
 * not by id, not by query parameter — because these are the notes people write
 * to themselves and there is no reason for a colleague to read them.
 *
 * No capability gate beyond being signed in. A capability would imply somebody
 * could be denied their own to-do list, which is not a thing an administrator
 * should be able to do.
 *
 * NOT a destination. These endpoints feed a tab inside Home; there is no
 * global task list, no `/tasks` route and no sidebar entry, which is the
 * settled IA decision the story restates.
 */
final class PersonalWorkController extends Controller
{
    public function __construct(
        private readonly CreateTask $createTask,
        private readonly SetTaskCompletion $setCompletion,
        private readonly CreateReminder $createReminder,
    ) {}

    /**
     * My tasks, open ones first and the soonest at the top.
     *
     * Completed tasks are included but sorted below: they are the evidence the
     * work happened, and hiding them makes an agent wonder whether the tick
     * registered.
     */
    public function tasks(Request $request): JsonResponse
    {
        $userId = $this->ownerId($request);

        $tasks = Task::query()
            ->where('user_id', $userId)
            ->orderByRaw('case when completed_at is null then 0 else 1 end')
            /*
             * Nulls last among the open ones. A task with no due date is not
             * more urgent than one due in an hour, and most databases sort
             * null first if left to themselves.
             */
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return new JsonResponse([
            'data' => $this->shapeTasks($tasks->all()),
        ]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:'.CreateTask::MAXIMUM_TITLE],
            'ticket_id' => ['nullable', 'string', 'ulid'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = $this->createTask->handle(
            $this->ownerId($request),
            (string) $data['title'],
            isset($data['ticket_id']) && is_string($data['ticket_id']) ? $data['ticket_id'] : null,
            isset($data['due_at']) && is_string($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : null,
        );

        return new JsonResponse(['data' => $this->shapeTasks([$task])[0]], 201);
    }

    public function updateTask(Request $request, string $task): JsonResponse
    {
        $data = $request->validate([
            // The ONLY editable field. A task has no description, no assignee
            // and no sub-tasks to edit, and its title is what it is.
            'completed' => ['required', 'boolean'],
        ]);

        $updated = $this->setCompletion->handle(
            $this->ownerId($request),
            $task,
            (bool) $data['completed'],
        );

        return new JsonResponse(['data' => $this->shapeTasks([$updated])[0]]);
    }

    public function storeReminder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'remind_at' => ['required', 'date'],
            'ticket_id' => ['nullable', 'string', 'ulid'],
            'task_id' => ['nullable', 'string', 'ulid'],
        ]);

        $reminder = $this->createReminder->handle(
            $this->ownerId($request),
            CarbonImmutable::parse((string) $data['remind_at']),
            isset($data['ticket_id']) && is_string($data['ticket_id']) ? $data['ticket_id'] : null,
            isset($data['task_id']) && is_string($data['task_id']) ? $data['task_id'] : null,
        );

        return new JsonResponse([
            'data' => [
                'id' => (string) $reminder->getKey(),
                'remind_at' => $reminder->remind_at?->toIso8601ZuluString(),
                'ticket_id' => $reminder->ticket_id === null ? null : (string) $reminder->ticket_id,
                'task_id' => $reminder->task_id === null ? null : (string) $reminder->task_id,
            ],
        ], 201);
    }

    /**
     * The notes that named me, newest first.
     *
     * Each carries enough to open the ticket AT the note — the reference, the
     * subject and the message id — because the whole content of a mention is
     * "come and read this sentence", and landing at the top of a long thread
     * asks somebody to search for it.
     */
    public function mentions(Request $request): JsonResponse
    {
        $userId = $this->ownerId($request);

        $rows = DB::table('note_mentions')
            ->join('ticket_messages', 'ticket_messages.id', '=', 'note_mentions.message_id')
            ->join('tickets', 'tickets.id', '=', 'note_mentions.ticket_id')
            ->where('note_mentions.user_id', $userId)
            ->orderByDesc('note_mentions.created_at')
            ->limit(50)
            ->get([
                'note_mentions.id as id',
                'note_mentions.message_id as message_id',
                'note_mentions.ticket_id as ticket_id',
                'note_mentions.read_at as read_at',
                'note_mentions.created_at as mentioned_at',
                'ticket_messages.author_name as author_name',
                'ticket_messages.body as body',
                'tickets.reference as reference',
                'tickets.subject as subject',
            ]);

        return new JsonResponse([
            'data' => $rows->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'message_id' => (string) $row->message_id,
                'ticket_id' => (string) $row->ticket_id,
                'reference' => (string) $row->reference,
                'subject' => (string) $row->subject,
                'author_name' => (string) $row->author_name,
                /*
                 * A quoted extract, not the whole note. Home is a queue, and a
                 * long internal note pasted into it pushes every other row off
                 * the screen — the row is an invitation to open the ticket.
                 */
                'excerpt' => mb_substr((string) $row->body, 0, 240),
                'read_at' => $row->read_at === null ? null : (string) $row->read_at,
                'mentioned_at' => (string) $row->mentioned_at,
            ])->all(),
        ]);
    }

    public function readMention(Request $request, string $mention): JsonResponse
    {
        $userId = $this->ownerId($request);

        $row = NoteMention::query()->whereKey($mention)->first();

        // Somebody else's mention is not found, for the same reason somebody
        // else's task is not found.
        if ($row === null || (int) $row->user_id !== $userId) {
            throw ProblemException::make(
                'tickets.mention_not_found',
                'Mention not found',
                404,
                'That mention does not exist, or it is not yours.',
            );
        }

        if ($row->read_at === null) {
            $row->forceFill(['read_at' => now()])->save();
        }

        return new JsonResponse(['data' => ['id' => (string) $row->getKey(), 'read_at' => $row->read_at?->toIso8601ZuluString()]]);
    }

    /**
     * @param  list<Task>  $tasks
     * @return list<array<string, mixed>>
     */
    private function shapeTasks(array $tasks): array
    {
        $ticketIds = array_values(array_unique(array_filter(
            array_map(static fn (Task $task): ?string => $task->ticket_id === null ? null : (string) $task->ticket_id, $tasks),
        )));

        /*
         * One lookup for the page, not one per row. A task list is mostly
         * about a handful of tickets, and a per-row join turns a 200-task page
         * into 200 queries.
         */
        $tickets = $ticketIds === []
            ? collect()
            : DB::table('tickets')->whereIn('id', $ticketIds)->get(['id', 'reference', 'subject'])->keyBy('id');

        return array_map(static function (Task $task) use ($tickets): array {
            $ticket = $task->ticket_id === null ? null : $tickets->get((string) $task->ticket_id);

            return [
                'id' => (string) $task->getKey(),
                'title' => (string) $task->title,
                'due_at' => $task->due_at?->toIso8601ZuluString(),
                'completed_at' => $task->completed_at?->toIso8601ZuluString(),
                /*
                 * Computed here, once, rather than in the browser. "Overdue"
                 * decided on the client is decided against the reader's clock,
                 * which is the one place it can silently be wrong.
                 */
                'overdue' => $task->isOverdue(),
                'ticket_id' => $task->ticket_id === null ? null : (string) $task->ticket_id,
                'ticket_reference' => $ticket === null ? null : (string) $ticket->reference,
                'ticket_subject' => $ticket === null ? null : (string) $ticket->subject,
            ];
        }, $tasks);
    }

    private function ownerId(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        if ($id === null) {
            throw ProblemException::make(
                'security.unauthenticated',
                'Sign in first',
                401,
                'These are your own tasks; there is nobody to show them to.',
            );
        }

        return (int) $id;
    }
}
