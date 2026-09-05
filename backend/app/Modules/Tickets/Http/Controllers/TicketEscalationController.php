<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Commands\EscalateTicket;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use App\Modules\Tickets\Http\Resources\TicketResource;
use App\Modules\Tickets\Notifications\TicketEscalated;
use App\Modules\Tickets\Notifications\TicketNotifier;
use App\Modules\Tickets\Http\ActorResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Somebody says a ticket is going wrong.
 *
 * A POST to a named sub-resource rather than a PATCH on the ticket, and the
 * distinction is not cosmetic: a PATCH carries `If-Match` and would be refused
 * when somebody else had touched the ticket first. Escalation must never be
 * refused for that reason — two people escalating the same ticket agree, and
 * the second one is the confirmation, not the conflict.
 */
final class TicketEscalationController extends Controller
{
    public function __construct(
        private readonly EscalateTicket $escalate,
        private readonly ActorResolver $actors,
        private readonly TicketNotifier $notifier,
    ) {}

    /**
     * @response array<string, mixed>
     */
    public function store(Request $request, string $ticket): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:'.EscalateTicket::MAXIMUM_REASON],
        ]);

        $this->assertVisible($request, $ticket);

        $actor = $this->actors->fromRequest($request);

        $result = $this->escalate->handle($actor, $ticket, (string) $validated['reason']);

        /*
         * Only the FIRST escalation tells anybody.
         *
         * A second person pressing Escalate gets a 200 and the same ticket
         * back — they have not failed at anything — but the supervisors are
         * not alerted twice about one problem. Alerting on every press is how
         * a useful signal becomes one people filter out.
         */
        if ($result['escalated']) {
            $this->notifySupervisors($result['ticket'], $actor->label());
        }

        return new JsonResponse(TicketResource::toArray($result['ticket']));
    }

    private function notifySupervisors(Ticket $ticket, string $escalatedBy): void
    {
        $facts = $this->notifier->ticketFacts((string) $ticket->getKey());

        if ($facts === null) {
            return;
        }

        $this->notifier->notifyDepartmentSupervisors(
            $facts['department_id'],
            // Not the escalator, if they happen to be a supervisor: they know.
            $facts['assignee_id'],
            new TicketEscalated(
                $facts['id'],
                $facts['reference'],
                $facts['subject'],
                escalatedBy: $escalatedBy,
                reason: (string) $ticket->escalation_reason,
            ),
        );
    }

    /**
     * 404 for a ticket the caller may not see — never 403.
     *
     * A 403 would confirm the ticket exists, which is itself information: an
     * agent could walk ids and learn how many tickets the business has.
     */
    private function assertVisible(Request $request, string $ticketId): void
    {
        $query = Ticket::query()->whereKey($ticketId);

        $actor = $request->user();

        if ($actor !== null) {
            TicketVisibility::scopeForActor($query, $actor);
        }

        if ($query->exists()) {
            return;
        }

        throw ProblemException::make(
            'tickets.not_found',
            'Ticket not found',
            404,
            "No ticket with id [{$ticketId}].",
        );
    }
}
