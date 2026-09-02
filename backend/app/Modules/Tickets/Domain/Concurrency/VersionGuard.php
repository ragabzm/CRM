<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Concurrency;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Ticket;

/**
 * Refuses a change based on a version of the ticket that has moved on.
 *
 * Two agents open the same ticket. One sets it to Resolved; the other, still
 * looking at the old screen, sets the priority to High. Without this guard the
 * second write silently reverts the first, and nobody finds out until a
 * customer asks why their resolved ticket is open again.
 *
 * DELIBERATELY NARROW. The guard fires only for the five properties two people
 * genuinely contend over. Appending a message or an attachment is not guarded,
 * because two people writing different messages have not conflicted — they have
 * both said something, and both should be kept.
 *
 * Do not widen `CONTENDED` to "every column". Doing so would make a message
 * append fail whenever anyone else touched the ticket, which is the normal case
 * on a busy ticket and would train people to retry blindly.
 */
final class VersionGuard
{
    /**
     * The properties where a lost update actually matters.
     *
     * @var list<string>
     */
    public const CONTENDED = ['status', 'priority', 'category_id', 'assignee_id', 'department_id'];

    /**
     * @param  array<string, mixed>  $changes  The proposed change set.
     *
     * @throws ProblemException 409 when the submitted version is stale.
     */
    public function apply(Ticket $ticket, ?int $submittedVersion, array $changes): void
    {
        if (! self::touchesContended($changes)) {
            return;
        }

        if ($submittedVersion !== null && $ticket->version === $submittedVersion) {
            return;
        }

        /*
         * The refusal carries the CURRENT values, not just the version. The
         * client's next move is always "show me what it actually says now", and
         * making them fetch again costs a round trip and a window in which it
         * changes a third time.
         */
        throw ProblemException::make(
            'tickets.stale_version',
            'This ticket was changed by someone else',
            409,
            'Someone else changed this ticket while you were editing it. Reload to see the current version.',
            [
                'ticket_id' => (string) $ticket->getKey(),
                'current_version' => $ticket->version,
                'submitted_version' => $submittedVersion,
                'current' => self::contendedValues($ticket),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function touchesContended(array $changes): bool
    {
        return array_intersect_key($changes, array_flip(self::CONTENDED)) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function contendedValues(Ticket $ticket): array
    {
        return [
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'category_id' => $ticket->category_id,
            'assignee_id' => $ticket->assignee_id,
            'department_id' => $ticket->department_id,
        ];
    }
}
