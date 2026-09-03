<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Application\Timeline\CustomerTimelineQuery;
use App\Modules\Tickets\Http\Requests\CustomerTimelineRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * A customer's whole interaction history, newest first.
 *
 * Lives in Tickets (T3), not Customers (T2), even though the URL is keyed by
 * customer. Tickets owns the rows being read, and Tickets may depend on
 * Customers because that runs DOWNWARD; the reverse would invert the tier
 * graph. The path reads the way a person thinks about it, and the ownership
 * follows the data.
 */
final class CustomerTimelineController extends Controller
{
    public function __construct(private readonly CustomerTimelineQuery $query) {}

    /**
     * @response array{data: array<int, array{id:string,kind:string,ticket_id:string,ticket_ref:string,occurred_at:string,preview:string|null}>, next_cursor: string|null, has_more: bool}
     */
    public function index(CustomerTimelineRequest $request, string $customer): JsonResponse
    {
        /*
         * The customer is confirmed to exist before the feed is built. Without
         * this an unknown id would return an empty timeline — which reads as
         * "this customer has no history" rather than "there is no such
         * customer", and sends the agent looking for a bug that is not there.
         */
        if (! DB::table('customers')->where('id', $customer)->exists()) {
            throw ProblemException::make(
                'customers.not_found',
                'Customer not found',
                404,
                "No customer with id [{$customer}].",
            );
        }

        $page = $this->query->paginate($customer, $request->cursor(), $request->limit());

        return new JsonResponse($page->toArray());
    }
}
