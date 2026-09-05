<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain\Escalation;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\EscalateTicket;
use App\Modules\Tickets\Domain\Commands\RaisePriorityOneStep;
use App\Modules\Tickets\Notifications\TicketEscalated;
use App\Modules\Tickets\Notifications\TicketNotifier;

/**
 * The one automatic escalation condition there is.
 *
 * A missed target escalates the ticket, tells the department's supervisors,
 * and — only where a setting says so — raises the priority one step. That is
 * the whole of it. There is no rule table, no condition builder, no ordering
 * and no per-rule switch: one condition and two fixed actions.
 *
 * This lives in Sla and calls DOWN into Tickets' named commands. Sla is T4 and
 * Tickets is T3, so the direction is legal — and the commands are the point:
 * the sweep must not write `escalated_at` itself, or the history entry, the
 * lock and the reason validation would all have to exist here again.
 *
 * Idempotency is not implemented here. `EscalateTicket` refuses to escalate a
 * ticket twice and says so in its return value, so a ticket that breaches its
 * response target at 09:00 and its resolution target at 17:00 is escalated
 * once — and the minutely sweep running a hundred times over that day
 * notifies nobody a second time.
 */
final class EscalateOnBreach
{
    /**
     * What the history and the notification call the escalator.
     *
     * Not "System". A supervisor reading "System escalated this" learns
     * nothing; `sla_breach` names the thing that decided.
     */
    public const REASON = 'sla_breach';

    public function __construct(
        private readonly EscalateTicket $escalate,
        private readonly RaisePriorityOneStep $raisePriority,
        private readonly TicketNotifier $notifier,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @param  string  $target  `response` | `resolution` — which promise was missed.
     * @return bool  True when this call is the one that escalated it.
     */
    public function handle(string $ticketId, string $target): bool
    {
        $actor = Actor::system(self::REASON);

        $result = $this->escalate->handle(
            $actor,
            $ticketId,
            $this->reasonFor($target),
        );

        if (! $result['escalated']) {
            /*
             * Already escalated — by an earlier breach, or by a person who saw
             * it coming. Nothing is written and nobody is told again, which is
             * what makes running this every minute safe.
             */
            return false;
        }

        if ((bool) $this->settings->get('sla.breach_raises_priority')) {
            // Silently a no-op at Urgent. See `Priority::raisedOneStep`.
            $this->raisePriority->handle($actor, $result['ticket']);
        }

        $this->notifySupervisors($ticketId, $result['ticket']->escalation_reason ?? '');

        return true;
    }

    /**
     * Why, in words a supervisor can act on.
     *
     * Written in English here rather than translated, because it is stored on
     * the ticket and read by whoever opens it next — the notification is what
     * gets rendered per recipient, and it carries this text as the reason a
     * colleague wrote. Translating a stored reason would mean the ticket said
     * different things to different readers about the same event.
     */
    private function reasonFor(string $target): string
    {
        return $target === 'response'
            ? 'The first-reply target was missed.'
            : 'The resolution target was missed.';
    }

    private function notifySupervisors(string $ticketId, string $reason): void
    {
        $facts = $this->notifier->ticketFacts($ticketId);

        if ($facts === null) {
            return;
        }

        $this->notifier->notifyDepartmentSupervisors(
            $facts['department_id'],
            // Nobody to exclude: no person did this.
            null,
            new TicketEscalated(
                $facts['id'],
                $facts['reference'],
                $facts['subject'],
                escalatedBy: 'System',
                reason: $reason,
            ),
        );
    }
}
