<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Http\ActorResolver;
use App\Modules\Tickets\Http\Requests\StorePortalTicketRequest;
use App\Modules\Tickets\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A customer opening their own ticket.
 *
 * The same CreateTicket command the agent path uses, so the reference, the
 * event and the version all behave identically. What differs is where the
 * customer comes from and what the channel is — and both are decided HERE,
 * never taken from the request.
 */
final class PortalTicketsController extends Controller
{
    public function __construct(
        private readonly ActorResolver $actors,
        private readonly CreateTicket $create,
    ) {}

    /**
     * @response array<string, mixed>
     */
    public function store(StorePortalTicketRequest $request): JsonResponse
    {
        $data = $request->validated();

        $ticket = $this->create->handle(
            $this->actors->portalFromRequest($request),
            new CreateTicketInput(
                subject: (string) $data['subject'],
                description: (string) $data['description'],
                /*
                 * From the ACCOUNT, never the body. A customer_id the caller
                 * supplied would let any portal user open — and then read — a
                 * ticket against anybody else's record.
                 */
                customerId: $this->customerFor($request),
                // Fixed, not chosen. A portal submission that could claim
                // `channel: agent` would misreport where the work came from.
                channel: TicketChannel::Portal,
            ),
        );

        return new JsonResponse(TicketResource::toArray($ticket), 201);
    }

    /**
     * The signed-in customer's own tickets.
     *
     * @response array{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $this->customerFor($request);

        /*
         * Scoped by customer_id directly rather than through TicketVisibility.
         * That helper reads spatie roles, and a portal account has none — see
         * .squad/gaps/12-503.md. Filtering on the column the account actually
         * carries is the honest version of the same rule.
         */
        $tickets = Ticket::query()
            ->where(TicketVisibility::CUSTOMER_COLUMN, $customerId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return new JsonResponse([
            'data' => $tickets->map(static fn (Ticket $t) => TicketResource::toArray($t))->all(),
        ]);
    }

    private function customerFor(Request $request): string
    {
        $account = $request->user('portal');
        $customerId = $account?->getAttribute('customer_id');

        if (! is_string($customerId) || $customerId === '') {
            /*
             * Refused rather than defaulted. An account nobody has linked to a
             * customer has no business opening tickets for one, and guessing
             * would attach a stranger's message to somebody's record.
             */
            throw ProblemException::make(
                'tickets.portal_account_unlinked',
                'This account is not linked to a customer',
                403,
                'Contact support to finish setting up your account.',
            );
        }

        return $customerId;
    }
}
