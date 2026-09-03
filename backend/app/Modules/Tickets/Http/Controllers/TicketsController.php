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
use App\Modules\Tickets\Contracts\SlaReader;
use App\Modules\Tickets\Domain\Query\TicketCounts;
use App\Modules\Tickets\Domain\Query\TicketListQuery;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\Category;
use App\Modules\Tickets\Http\ActorResolver;
use App\Modules\Tickets\Http\AssigneeDirectory;
use App\Modules\Tickets\Http\Requests\AssignTicketRequest;
use App\Modules\Tickets\Http\Requests\ListTicketsRequest;
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
        /*
         * Through the CONTRACT, not the Sla module's classes. Sla is T4 and
         * this is T3; depending on the interface is what keeps the dependency
         * pointing downward, and it means a deployment can switch the engine
         * off by swapping one binding.
         */
        private readonly SlaReader $sla,
        /*
         * Names for a whole page in one query. A ticket carries an
         * `assignee_id`, and a list that shipped only the id left the client
         * with nothing to render — the Assignee column showed a dash for every
         * assigned ticket, which reads as "nobody has this".
         */
        private readonly AssigneeDirectory $people,
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
     * The ticket list.
     *
     * Read-only, so no Idempotency-Key. Scoped by TicketVisibility before any
     * caller-supplied filter is applied — an agent who puts a colleague's id in
     * the URL gets their own tickets back rather than a refusal, because the
     * filter narrows what they may see and never widens it.
     *
     * @response array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function index(ListTicketsRequest $request, TicketListQuery $query): JsonResponse
    {
        $page = $query->paginate($request->toFilters(), $request->user());

        /** @var list<Ticket> $tickets */
        $tickets = $page->items();

        // One call for the page, not one per row.
        $sla = $this->sla->forTickets(array_map(
            static fn (Ticket $ticket): string => (string) $ticket->getKey(),
            $tickets,
        ));

        return new JsonResponse([
            'data' => array_map(
                static fn (Ticket $ticket): array => TicketResource::toArray(
                    $ticket,
                    $sla[(string) $ticket->getKey()] ?? null,
                ),
                $tickets,
            ),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
            /*
             * Names for the ids on this page, resolved once.
             *
             * Side-loaded as maps rather than repeated on every row: a page of
             * twenty-five tickets is mostly the same four agents and the same
             * five categories, and the client already indexes by id.
             *
             * It is here rather than left to the client because the client
             * cannot get it: `/users` and `/admin/categories` are both behind
             * capabilities an ordinary agent does not hold, so a list that
             * shipped only ids was a list an agent could never render.
             */
            'included' => [
                'assignees' => $this->people->namesFor(array_values(array_unique(array_filter(
                    array_map(static fn (Ticket $ticket): ?string => $ticket->assignee_id === null
                        ? null
                        : (string) $ticket->assignee_id, $tickets),
                )))),
                'categories' => self::categoryNames($tickets, $request->user()?->preferredLocale()),
            ],
        ]);
    }

    /**
     * The five numbers on the agent's home screen.
     *
     * One aggregate query. Five round trips would be five times the load for a
     * strip of numbers, taken at five slightly different moments — so they
     * could disagree with each other and with the list they link to.
     *
     * @response array<string, int|null>
     */
    public function counts(Request $request, TicketCounts $counts): JsonResponse
    {
        return new JsonResponse($counts->forActor($request->user()));
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

        /*
         * The version, also as an ETag.
         *
         * It is already in the body; this is the same fact in the form HTTP
         * understands, so a client can hand it straight back as `If-Match`
         * without unpacking JSON to find it. One source, two spellings — the
         * guard still reads exactly one number.
         *
         * Weak, because two responses with the same version are semantically
         * equivalent without being byte-identical (timestamps of related rows
         * can differ).
         */
        $sla = $this->sla->forTickets([(string) $found->getKey()]);

        return (new JsonResponse(TicketResource::toArray($found, $sla[(string) $found->getKey()] ?? null)))
            ->setEtag(self::etagFor($found->version), weak: true);
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
            $request->submittedVersion(),
            TicketAttributeChanges::fromValidated($data),
        );

        return (new JsonResponse(TicketResource::toArray($updated)))
            ->setEtag(self::etagFor($updated->version), weak: true);
    }

    /** The ticket version, spelled as an entity tag. */
    public static function etagFor(int $version): string
    {
        return (string) $version;
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

    /**
     * Category labels for the ids on this page, in the reader's language.
     *
     * Localised HERE rather than shipping both columns, because a client that
     * received `name_en` and `name_ar` would have to decide which to show —
     * and every list that forgot would quietly show English to an Arabic
     * reader.
     *
     * The language comes from the signed-in person's own preference, not from
     * `app()->getLocale()`. Nothing in this application sets the application
     * locale per request, so reading it would return the config default and
     * hand every Arabic reader an English column — which is exactly what it
     * did until this line named the right source.
     *
     * @param  list<Ticket>  $tickets
     * @return array<string, string>
     */
    private static function categoryNames(array $tickets, ?string $locale): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (Ticket $ticket): ?int => $ticket->category_id,
            $tickets,
        ))));

        if ($ids === []) {
            return [];
        }

        $column = $locale === 'ar' ? 'name_ar' : 'name_en';

        return Category::query()
            ->whereIn('id', $ids)
            ->pluck($column, 'id')
            ->mapWithKeys(static fn (string $name, mixed $id): array => [(string) $id => $name])
            ->all();
    }
}
