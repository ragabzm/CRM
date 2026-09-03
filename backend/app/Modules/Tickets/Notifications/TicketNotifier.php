<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decides who hears about what.
 *
 * Two rules that are easy to get wrong and annoying to live with:
 *
 *   NOBODY IS TOLD ABOUT THEIR OWN ACTION. An agent who assigns a ticket to
 *   themselves does not need an email saying they did. A system that sends one
 *   trains people to ignore its notifications, and then the one that mattered
 *   is ignored too.
 *
 *   A DEACTIVATED ACCOUNT IS NOT NOTIFIED. Mail to somebody who has left is
 *   both useless and a small leak — a ticket subject arriving at an address
 *   nobody watches any more.
 *
 * Failures never propagate. A notification is a courtesy on top of an action
 * that has already happened; refusing an assignment because a mail queue was
 * unreachable would make the courtesy more important than the work.
 */
final class TicketNotifier
{
    /**
     * Tells one person, unless they are the one who did it.
     */
    public function notifyAssignee(?int $userId, ?string $actorId, TicketNotification $notification): void
    {
        if ($userId === null) {
            return;
        }

        if ($actorId !== null && (string) $userId === $actorId) {
            // Their own doing. They already know.
            return;
        }

        $this->send([$userId], $notification);
    }

    /**
     * Tells the supervisors of a department.
     *
     * Only on a BREACH, and this is why the fan-out exists at all: an at-risk
     * ticket is the assignee's to save, while a missed target is the team's
     * problem and somebody has to be able to reassign it.
     */
    public function notifyDepartmentSupervisors(?int $departmentId, ?int $exceptUserId, TicketNotification $notification): void
    {
        if ($departmentId === null) {
            /*
             * A ticket with no department has no supervisors to escalate to.
             * Falling back to "every supervisor" would turn one late ticket
             * into an email to the whole management layer.
             */
            return;
        }

        $ids = User::query()
            ->where('department_id', $departmentId)
            ->when($exceptUserId !== null, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor'))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->send($ids, $notification);
    }

    /**
     * @param  list<int>  $userIds
     */
    private function send(array $userIds, TicketNotification $notification): void
    {
        if ($userIds === []) {
            return;
        }

        try {
            $recipients = User::query()
                ->whereIn('id', $userIds)
                // Mail to somebody who has left is useless and a small leak.
                ->where('is_active', true)
                ->get();

            foreach ($recipients as $recipient) {
                $recipient->notify($notification);
            }
        } catch (Throwable $e) {
            /*
             * Swallowed, and said so. A notification is a courtesy on top of an
             * action that already happened; refusing the action because the
             * courtesy failed would be the wrong way round.
             */
            Log::warning('Could not send a ticket notification.', [
                'notification' => $notification::class,
                'reason' => $e->getMessage(),
                'consequence' => 'The action itself is unaffected; nobody was told.',
            ]);
        }
    }

    /**
     * The three facts every notification needs about a ticket.
     *
     * Read through the query builder: this is Platform (T0) and Tickets is T3,
     * so it must not learn that module's model.
     *
     * @return array{id: string, reference: string, subject: string, assignee_id: int|null, department_id: int|null}|null
     */
    public function ticketFacts(string $ticketId): ?array
    {
        $row = DB::table('tickets')
            ->where('id', $ticketId)
            ->first(['id', 'reference', 'subject', 'assignee_id', 'department_id']);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'reference' => (string) $row->reference,
            'subject' => (string) $row->subject,
            'assignee_id' => $row->assignee_id === null ? null : (int) $row->assignee_id,
            'department_id' => $row->department_id === null ? null : (int) $row->department_id,
        ];
    }
}
