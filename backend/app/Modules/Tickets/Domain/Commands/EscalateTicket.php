<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Database\ConnectionInterface;

/**
 * Marks a ticket as going wrong, and says who thinks so and why.
 *
 * NOT part of `UpdateTicketAttributes`, and that is the whole design.
 *
 * Escalation carries no version and consumes none. Two people escalating the
 * same ticket in the same minute have not conflicted — they AGREE, and
 * refusing the second one because they were both looking at version 4 would be
 * refusing the very agreement that makes the ticket worth escalating. Every
 * other mutation goes through the version guard because two people changing
 * priority in opposite directions is a real conflict; this one does not,
 * because there is no opposite direction.
 *
 * It also leaves `version` alone. Bumping it would invalidate the screen of
 * every colleague reading the ticket, forcing them to reload to change
 * anything — for a change that altered nothing they were looking at.
 *
 * ONE escalation per ticket, whoever asks. A second call succeeds and writes
 * nothing: the ticket is already escalated, and the record keeps who first
 * raised it. That is what makes the minutely SLA sweep safe to run twice, and
 * it means a human and the sweep racing produce one escalation and one
 * notification rather than two of each.
 */
final class EscalateTicket
{
    /** A reason has to be long enough to tell somebody something. */
    private const MINIMUM_REASON = 3;

    public const MAXIMUM_REASON = 500;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TicketEventRecorder $history,
    ) {}

    /**
     * @return array{ticket: Ticket, escalated: bool}  `escalated` is false when
     *         it already was — the caller uses it to decide whether to notify.
     */
    public function handle(Actor $actor, string $ticketId, string $reason): array
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MINIMUM_REASON) {
            /*
             * Required, and required to say something. "Escalated" with no
             * reason reaches a supervisor as an alert they cannot act on
             * without opening the ticket and working out for themselves what
             * the problem was — which is the work the escalation was supposed
             * to save them.
             */
            throw ProblemException::make(
                'tickets.escalation_reason_required',
                'Say why',
                422,
                'An escalation without a reason reaches a supervisor as an alarm they cannot act on.',
            );
        }

        return $this->db->transaction(function () use ($actor, $ticketId, $reason): array {
            $ticket = Ticket::query()->whereKey($ticketId)->lockForUpdate()->first();

            if ($ticket === null) {
                throw ProblemException::make(
                    'tickets.not_found',
                    'Ticket not found',
                    404,
                    "No ticket with id [{$ticketId}].",
                );
            }

            if ($ticket->escalated_at !== null) {
                // Already ours. Succeeds, writes nothing, and tells the caller
                // not to notify anybody a second time.
                return ['ticket' => $ticket, 'escalated' => false];
            }

            $ticket->forceFill([
                'escalated_at' => now(),
                'escalated_by' => $actor->id(),
                'escalation_reason' => mb_substr($reason, 0, self::MAXIMUM_REASON),
            ])->save();

            /*
             * `before` names the state that ended, so the history reads as a
             * change rather than as an announcement. The version is unchanged
             * and recorded as such — a reader comparing event versions must
             * not conclude an edit was lost.
             */
            $this->history->record(
                (string) $ticket->getKey(),
                TicketEventKind::Escalated,
                $actor,
                ['escalated' => false],
                ['escalated' => true],
                ['reason' => $ticket->escalation_reason],
                $ticket->version,
            );

            return ['ticket' => $ticket, 'escalated' => true];
        });
    }
}
