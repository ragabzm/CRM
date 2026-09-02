<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Domain\Capabilities;
use App\Modules\Tickets\Domain\Commands\AssignTicket;
use App\Modules\Tickets\Domain\Commands\ChangeDepartment;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Commands\ReopenTicket;
use App\Modules\Tickets\Domain\Commands\ResolveTicket;
use App\Modules\Tickets\Domain\Commands\TicketAttributeChanges;
use App\Modules\Tickets\Domain\Commands\UpdateTicketAttributes;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Priority;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Http\ActorResolver;
use App\Modules\Tickets\Http\Requests\AssignTicketRequest;
use App\Modules\Tickets\Http\Requests\ChangeDepartmentRequest;
use App\Modules\Tickets\Http\Requests\ReopenTicketRequest;
use App\Modules\Tickets\Http\Requests\ResolveTicketRequest;
use App\Modules\Tickets\Http\Requests\StoreTicketRequest;
use App\Modules\Tickets\Http\Requests\UpdateTicketAttributesRequest;
use App\Modules\Tickets\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The HTTP surface over the ticket commands.
 *
 * Thin on purpose: every rule — the lifecycle, the version guard, the event
 * append — lives in the domain, so the console command and the queue worker
 * that call the same commands get the same behaviour.
 */
final class TicketsController extends Controller
{
    public function __construct(
        private readonly ActorResolver $actors,
        private readonly CreateTicket $create,
        private readonly UpdateTicketAttributes $update,
        private readonly AssignTicket $assign,
        private readonly ResolveTicket $resolve,
        private readonly ReopenTicket $reopen,
        private readonly ChangeDepartment $department,
    ) {}

    /**
     * @response array<string, mixed>
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $data = $request->validated();

        $ticket = $this->create->handle(
            $this->actors->fromRequest($request),
            new CreateTicketInput(
                subject: (string) $data['subject'],
                description: (string) $data['description'],
                customerId: (string) $data['customer_id'],
                channel: TicketChannel::from((string) $data['channel']),
                categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
                priority: isset($data['priority']) ? Priority::from((string) $data['priority']) : null,
                departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            ),
        );

        return new JsonResponse(TicketResource::toArray($ticket), 201);
    }

    /**
     * @response array<string, mixed>
     */
    public function show(Request $request, string $ticket): JsonResponse
    {
        $actor = $request->user();

        $query = Ticket::query()->whereKey($ticket);

        if ($actor !== null) {
            // The one row-level rule, applied at the query site. Never a global
            // scope — see TicketVisibility.
            TicketVisibility::scopeForActor($query, $actor);
        }

        $found = $query->first();

        if ($found === null) {
            /*
             * 404, not 403, for a ticket the caller may not see. A 403 would
             * confirm the ticket exists, which is itself information — an agent
             * could enumerate ids and learn how many tickets there are.
             */
            throw ProblemException::make(
                'tickets.not_found',
                'Ticket not found',
                404,
                "No ticket with id [{$ticket}].",
            );
        }

        return new JsonResponse(TicketResource::toArray($found));
    }

    /**
     * @response array<string, mixed>
     */
    public function updateAttributes(UpdateTicketAttributesRequest $request, string $ticket): JsonResponse
    {
        $data = $request->validated();

        $updated = $this->update->handle(
            $this->actors->fromRequest($request),
            $ticket,
            (int) $data['version'],
            TicketAttributeChanges::fromValidated($data),
        );

        return new JsonResponse(TicketResource::toArray($updated));
    }

    /**
     * @response array<string, mixed>
     */
    public function assign(AssignTicketRequest $request, string $ticket): JsonResponse
    {
        $data = $request->validated();

        $updated = $this->assign->handle(
            $this->actors->fromRequest($request),
            $ticket,
            (int) $data['version'],
            $data['assignee_id'] === null ? null : (int) $data['assignee_id'],
            /*
             * Whether this person may take work off a colleague. Resolved here
             * from the caller's capabilities and passed in, so the command
             * stays free of ambient auth — the sweep and the console call it
             * with no session at all.
             */
            $request->user()?->can(Capabilities::TICKET_REASSIGN_ANY) ?? false,
        );

        return new JsonResponse(TicketResource::toArray($updated));
    }

    /**
     * @response array<string, mixed>
     */
    public function resolveTicket(ResolveTicketRequest $request, string $ticket): JsonResponse
    {
        $data = $request->validated();

        $updated = $this->resolve->handle(
            $this->actors->fromRequest($request),
            $ticket,
            (int) $data['version'],
            (string) $data['resolution_note'],
        );

        return new JsonResponse(TicketResource::toArray($updated));
    }

    /**
     * @response array<string, mixed>
     */
    public function reopenTicket(ReopenTicketRequest $request, string $ticket): JsonResponse
    {
        $data = $request->validated();

        $updated = $this->reopen->handle(
            $this->actors->fromRequest($request),
            $ticket,
            (int) $data['version'],
            isset($data['reason']) ? (string) $data['reason'] : null,
        );

        return new JsonResponse(TicketResource::toArray($updated));
    }

    /**
     * Moves a ticket to another department.
     *
     * @response array<string, mixed>
     */
    public function changeDepartment(ChangeDepartmentRequest $request, string $ticket): JsonResponse
    {
        $data = $request->validated();

        $updated = $this->department->handle(
            $this->actors->fromRequest($request),
            $ticket,
            (int) $data['version'],
            (int) $data['department_id'],
        );

        return new JsonResponse(TicketResource::toArray($updated));
    }
}
