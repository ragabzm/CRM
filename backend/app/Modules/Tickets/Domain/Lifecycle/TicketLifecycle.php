<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Lifecycle;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;

/**
 * The time-dependent half of the lifecycle.
 *
 * `TicketTransitions` answers "is this edge in the graph?". This answers the two
 * questions that also depend on the clock and on who is asking: may this closed
 * ticket still be reopened, and which timestamps does this move set.
 *
 * Both settings are read at the moment they are needed rather than injected at
 * construction — an administrator who shortens the reopen window expects the
 * next request to obey it, not the next deploy.
 */
final class TicketLifecycle
{
    public const AUTO_CLOSE_SETTING = 'tickets.auto_close_window_hours';

    public const REOPEN_SETTING = 'tickets.reopen_window_days';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function autoCloseWindowHours(): int
    {
        return (int) $this->settings->get(self::AUTO_CLOSE_SETTING);
    }

    public function reopenWindowDays(): int
    {
        return (int) $this->settings->get(self::REOPEN_SETTING);
    }

    /**
     * Refuses a reopen that has aged out.
     *
     * The system actor is exempt. The sweep and the customer-reply listener act
     * on the product's own behalf and are never "too late" — only a person
     * reopening by hand is bound by the window.
     *
     * @throws ProblemException 409 when the window has passed.
     */
    public function assertMayLeaveClosed(Ticket $ticket, TicketStatus $target, Actor $actor): void
    {
        if ($ticket->status !== TicketStatus::Closed || $target === TicketStatus::Closed) {
            return;
        }

        if ($actor->kind() === 'system') {
            return;
        }

        $closedAt = $ticket->closed_at;

        if ($closedAt === null) {
            // Closed before this column existed. Refusing would strand those
            // tickets forever, so they stay reopenable.
            return;
        }

        $days = $this->reopenWindowDays();

        /*
         * The window ends when its last DAY ends, not at the exact instant of
         * closing plus N days.
         *
         * Comparing instants makes the boundary a microsecond cliff: a ticket
         * closed exactly 14 days ago is refused because `now()` has advanced a
         * few microseconds past the deadline computed from it. Worse, it means
         * "you have 14 days" really means "13 days and however many hours are
         * left today", which is not what anyone reads it as.
         */
        $deadline = $closedAt->addDays($days)->endOfDay();

        if (now()->lessThanOrEqualTo($deadline)) {
            return;
        }

        throw ProblemException::make(
            'tickets.reopen_window_expired',
            'This ticket closed too long ago to reopen',
            409,
            sprintf(
                'This ticket closed %s. Tickets can be reopened for %d days; after that, raise a new one so it gets its own history and its own clock.',
                $closedAt->diffForHumans(),
                $days,
            ),
            [
                'ticket_id' => (string) $ticket->getKey(),
                'closed_at' => $closedAt->toIso8601String(),
                'reopen_window_days' => $days,
                /*
                 * The refusal carries the way forward. "No" on its own leaves
                 * an agent with a customer on the line and nothing to offer.
                 */
                'new_request_hint' => [
                    'action' => 'create_ticket',
                    'path' => $this->newRequestPath($ticket, $actor),
                    'customer_id' => (string) $ticket->customer_id,
                ],

                /*
                 * The same way forward, under the name the portal reads.
                 *
                 * A customer who is told "no" with nothing else on the screen
                 * has been given a dead end — and the next thing they do is
                 * email support about not being able to email support.
                 */
                'new_request_url' => $this->newRequestPath($ticket, $actor),
            ],
        );
    }

    /**
     * Where "raise a new one" actually goes.
     *
     * Different surfaces, different routes. Handing a customer `/tickets/new`
     * would send them to a staff screen they cannot open — a dead end dressed
     * as a way forward, which is worse than no link at all.
     */
    private function newRequestPath(Ticket $ticket, Actor $actor): string
    {
        return $actor->kind() === 'portal'
            ? '/portal/requests/new?from='.$ticket->getKey()
            : '/tickets/new?from='.$ticket->getKey();
    }

    /**
     * The timestamp columns a transition sets.
     *
     * @return array<string, mixed>
     */
    public function stampsFor(Ticket $ticket, TicketStatus $target): array
    {
        return match ($target) {
            TicketStatus::Resolved => ['resolved_at' => now(), 'closed_at' => null],
            TicketStatus::Closed => ['closed_at' => now()],
            /*
             * Reopening clears both. A ticket that is open again has not been
             * resolved and has not been closed — leaving the stamps would make
             * the sweep close it again on its next run.
             */
            TicketStatus::Open => ['resolved_at' => null, 'closed_at' => null],
            TicketStatus::Pending => [],
        };
    }
}
