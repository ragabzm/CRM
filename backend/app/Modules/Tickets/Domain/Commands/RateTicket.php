<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Tickets\Contracts\CustomerRequestGateway;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Database\ConnectionInterface;

/**
 * The customer says whether it went well.
 *
 * Append-shaped, like escalation and for the same reason: satisfaction is not
 * one of the five contended properties, so this carries no version and bumps
 * none. Two staff members editing a ticket can conflict; a customer telling us
 * what they thought cannot conflict with anything.
 *
 * A BOOLEAN, and there is no scale anywhere below. No range, no midpoint, no
 * scale identifier, no version and nothing to convert — which is what makes
 * "compare this quarter to last" a question with one honest answer rather than
 * a normalisation argument.
 *
 * The window is the interesting rule. A rating can be changed while it is
 * fresh, because somebody who tapped the wrong one within a minute meant the
 * other, and refusing them makes the figure wrong for ever. After the window it
 * is locked — and locked LOUDLY, with the reason, rather than silently
 * swallowing the tap and leaving them wondering whether it registered.
 */
final class RateTicket
{
    public const WINDOW_SETTING = 'tickets.rating_change_window_hours';

    /** Declared on the contract, so the portal can read it without importing this. */
    public const MAXIMUM_COMMENT = CustomerRequestGateway::MAXIMUM_COMMENT;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TicketEventRecorder $history,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @param  bool  $positive  Thumbs up, or thumbs down. There is no third value.
     * @param  string|null  $comment  Optional, always. Rating alone is complete.
     */
    public function handle(Actor $actor, string $ticketId, bool $positive, ?string $comment = null): Ticket
    {
        return $this->db->transaction(function () use ($actor, $ticketId, $positive, $comment): Ticket {
            $ticket = Ticket::query()->whereKey($ticketId)->lockForUpdate()->first();

            if ($ticket === null) {
                throw ProblemException::make(
                    'tickets.not_found',
                    'Ticket not found',
                    404,
                    "No ticket with id [{$ticketId}].",
                );
            }

            $this->assertFinished($ticket);
            $this->assertWithinTheWindow($ticket);

            $before = $ticket->satisfaction;

            $ticket->forceFill([
                'satisfaction' => $positive,
                /*
                 * A blank comment clears the old one rather than keeping it.
                 * Somebody changing their rating and deleting what they wrote
                 * has withdrawn the words, and leaving them attached to the
                 * opposite verdict would misrepresent them.
                 */
                'satisfaction_comment' => $this->cleanComment($comment),
                'satisfaction_at' => now(),
            ])->save();

            $this->history->record(
                (string) $ticket->getKey(),
                TicketEventKind::Rated,
                $actor,
                ['satisfaction' => $before],
                ['satisfaction' => $positive],
                $ticket->satisfaction_comment === null ? null : ['comment' => $ticket->satisfaction_comment],
                // Unchanged, and recorded as such: a reader comparing event
                // versions must not conclude an edit was lost.
                $ticket->version,
            );

            return $ticket;
        });
    }

    /**
     * How long a rating can be changed for. Zero locks it immediately.
     */
    public function windowHours(): int
    {
        return (int) $this->settings->get(self::WINDOW_SETTING);
    }

    /** Whether this ticket can still be rated or re-rated, right now. */
    public function isOpenForRating(Ticket $ticket): bool
    {
        if (! $this->isFinished($ticket)) {
            return false;
        }

        if ($ticket->satisfaction_at === null) {
            return true;
        }

        return $ticket->satisfaction_at->addHours($this->windowHours())->isFuture();
    }

    private function isFinished(Ticket $ticket): bool
    {
        return in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true);
    }

    private function assertFinished(Ticket $ticket): void
    {
        if ($this->isFinished($ticket)) {
            return;
        }

        /*
         * Asking somebody how it went while it is still going is asking them
         * to judge unfinished work — and their answer would be about the wait,
         * not the outcome.
         */
        throw ProblemException::make(
            'tickets.not_finished',
            'This request is still open',
            422,
            'You can say how it went once it has been resolved.',
        );
    }

    private function assertWithinTheWindow(Ticket $ticket): void
    {
        if ($ticket->satisfaction_at === null) {
            // Never rated. The window governs CHANGES, not the first answer.
            return;
        }

        $closesAt = $ticket->satisfaction_at->addHours($this->windowHours());

        if ($closesAt->isFuture()) {
            return;
        }

        /*
         * Refused with the reason, never silently ignored. A tap that appears
         * to do nothing is worse than one that is refused: the person taps
         * again, then assumes the product is broken, and they are right to.
         */
        throw ProblemException::make(
            'tickets.rating_locked',
            'This can no longer be changed',
            409,
            'Your answer was recorded and the time to change it has passed.',
            ['locked_at' => $closesAt->toIso8601String()],
        );
    }

    private function cleanComment(?string $comment): ?string
    {
        $trimmed = trim((string) $comment);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, self::MAXIMUM_COMMENT);
    }
}
