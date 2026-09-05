<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal;

use App\Models\User;
use App\Modules\Tickets\Notifications\ReminderDue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fires the reminders whose moment has come, exactly once each.
 *
 * IDEMPOTENCE IS THE WHOLE DESIGN. `fired_at` is written in the SAME
 * transaction as the dispatch and the row is locked while that happens, so a
 * second sweep — a minute that ran long, two workers, a retried job — selects
 * nothing rather than sending the same reminder again. A reminder that arrives
 * twice is worse than one that arrives late: the second one makes the person
 * check whether they missed something.
 *
 * Marked fired even when the notification could not be queued. The alternative
 * is a row that retries every minute for ever, and an owner whose inbox fills
 * with one reminder the moment the mail host comes back.
 */
final class ReminderSweep
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /** @return int How many fired. */
    public function run(): int
    {
        $due = Reminder::query()
            ->whereNull('fired_at')
            ->where('remind_at', '<=', now())
            /*
             * Oldest first. If a backlog has built up, the reminder somebody
             * has been waiting longest for should not be the last to arrive.
             */
            ->orderBy('remind_at')
            ->pluck('id');

        $fired = 0;

        foreach ($due as $id) {
            if ($this->fire((string) $id)) {
                $fired++;
            }
        }

        return $fired;
    }

    private function fire(string $id): bool
    {
        return $this->db->transaction(function () use ($id): bool {
            $reminder = Reminder::query()->whereKey($id)->lockForUpdate()->first();

            /*
             * Re-read inside the lock. Between the list above and this line
             * another sweep may have fired it — which is exactly the race the
             * lock and this check exist to lose safely.
             */
            if ($reminder === null || $reminder->fired_at !== null) {
                return false;
            }

            $reminder->forceFill(['fired_at' => now()])->save();

            $this->notify($reminder);

            return true;
        });
    }

    private function notify(Reminder $reminder): void
    {
        try {
            $owner = User::query()
                ->whereKey($reminder->user_id)
                // Somebody who has left gets no reminders. The row is still
                // marked fired, so it does not sit in the sweep for ever.
                ->where('is_active', true)
                ->first();

            if ($owner === null) {
                return;
            }

            $about = $this->describe($reminder);

            if ($about === null) {
                /*
                 * The ticket or task it pointed at is gone. Nothing to remind
                 * anybody about, and a notification saying "something" is
                 * worse than silence.
                 */
                return;
            }

            $owner->notify(new ReminderDue(
                (string) $reminder->getKey(),
                $about['about'],
                $about['ticket_id'],
                $about['reference'],
            ));
        } catch (Throwable $e) {
            /*
             * Swallowed inside the transaction ON PURPOSE, so a failure to
             * queue does not roll back `fired_at`. Letting it roll back would
             * retry this reminder every minute until the mail host returned,
             * and then deliver the whole backlog at once.
             */
            Log::warning('Could not send a due reminder.', [
                'reminder_id' => (string) $reminder->getKey(),
                'reason' => $e->getMessage(),
                'consequence' => 'The reminder is marked fired and will not be retried.',
            ]);
        }
    }

    /**
     * What the reminder is about, in the owner's own words.
     *
     * @return array{about: string, ticket_id: string|null, reference: string|null}|null
     */
    private function describe(Reminder $reminder): ?array
    {
        if ($reminder->ticket_id !== null) {
            $ticket = DB::table('tickets')
                ->where('id', $reminder->ticket_id)
                ->first(['id', 'reference', 'subject']);

            return $ticket === null ? null : [
                'about' => (string) $ticket->subject,
                'ticket_id' => (string) $ticket->id,
                'reference' => (string) $ticket->reference,
            ];
        }

        $task = Task::query()->whereKey($reminder->task_id)->first();

        return $task === null ? null : [
            'about' => (string) $task->title,
            // A task's own ticket, when it has one, so the notification opens
            // the work rather than a list.
            'ticket_id' => $task->ticket_id === null ? null : (string) $task->ticket_id,
            'reference' => null,
        ];
    }
}
