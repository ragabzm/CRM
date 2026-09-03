<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Who this ticket is for, and what else they have open.
 *
 * The point of the panel is that an agent answers a person, not a ticket:
 * knowing this is their fourth request this month changes the reply. That is
 * only true if it costs nothing to know — a panel that adds a second of
 * latency to every ticket gets collapsed and then ignored.
 *
 * Hence ONE query. Counts as correlated subqueries rather than as three round
 * trips, and no Eloquent relation loading, which is where the N+1 would come
 * back the first time someone added a field.
 */
final class CustomerContextController extends Controller
{
    /** "Recent" for a support conversation. Long enough to spot a pattern. */
    private const RECENT_DAYS = 30;

    /**
     * @response array<string, mixed>
     */
    public function show(Request $request, string $ticket): JsonResponse
    {
        $customerId = $this->customerIdOf($request, $ticket);

        $open = array_map(
            static fn (TicketStatus $s): string => $s->value,
            TicketStatus::openStates(),
        );

        $context = DB::table('customers as c')
            ->where('c.id', $customerId)
            ->selectRaw('c.id, c.reference, c.full_name, c.state, c.department_id')
            ->selectSub(
                DB::table('tickets')
                    ->selectRaw('count(*)')
                    ->whereColumn('tickets.customer_id', 'c.id')
                    ->whereIn('status', $open),
                'open_ticket_count',
            )
            ->selectSub(
                DB::table('tickets')
                    ->selectRaw('count(*)')
                    ->whereColumn('tickets.customer_id', 'c.id')
                    ->where('created_at', '>=', now()->subDays(self::RECENT_DAYS)),
                'recent_ticket_count',
            )
            ->selectSub(
                // The last time this person and the business actually spoke —
                // not the last time a row changed, which a background sweep
                // would keep bumping forever.
                DB::table('ticket_messages')
                    ->selectRaw('max(sent_at)')
                    ->whereColumn('ticket_messages.customer_id', 'c.id'),
                'last_interaction_at',
            )
            ->selectSub(
                DB::table('departments')
                    ->selectRaw('name')
                    ->whereColumn('departments.id', 'c.department_id')
                    ->limit(1),
                'department_name',
            )
            ->first();

        if ($context === null) {
            throw ProblemException::make(
                'customers.not_found',
                'Customer not found',
                404,
                'The ticket names a customer that no longer exists.',
            );
        }

        return new JsonResponse([
            'customer_id' => (string) $context->id,
            'reference' => $context->reference,
            'full_name' => $context->full_name,
            'state' => $context->state,
            'department' => $context->department_id === null ? null : [
                'id' => (int) $context->department_id,
                'name' => $context->department_name,
            ],
            'open_ticket_count' => (int) $context->open_ticket_count,
            'recent_ticket_count' => (int) $context->recent_ticket_count,
            'recent_window_days' => self::RECENT_DAYS,
            'last_interaction_at' => $context->last_interaction_at === null
                ? null
                : \Illuminate\Support\Carbon::parse($context->last_interaction_at)->toIso8601ZuluString(),
        ]);
    }

    /**
     * The ticket's customer — after checking the caller may see the ticket.
     *
     * 404 for a ticket the caller cannot see, never 403: a 403 would confirm it
     * exists, and this endpoint would otherwise be a way to learn a customer's
     * name and volume from a ticket id alone.
     */
    private function customerIdOf(Request $request, string $ticket): string
    {
        $actor = $request->user();

        $query = Ticket::query()->whereKey($ticket);

        if ($actor !== null) {
            TicketVisibility::scopeForActor($query, $actor);
        }

        $customerId = $query->value('customer_id');

        if ($customerId === null) {
            throw ProblemException::make(
                'tickets.not_found',
                'Ticket not found',
                404,
                'No ticket matches this identifier.',
            );
        }

        return (string) $customerId;
    }
}
