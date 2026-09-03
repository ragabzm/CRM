<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Contracts\CustomerRequestGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The customer's own requests.
 *
 * Everything here is scoped by a customer id taken from the SIGNED-IN ACCOUNT
 * and never from the request. That is the whole data boundary: there is no
 * parameter a caller could set to ask about somebody else, so there is no
 * check to forget.
 *
 * Reads and writes go through `CustomerRequestGateway` — Tickets' own narrow
 * contract — so the portal never touches a ticket row and never decides what a
 * customer may see.
 */
final class PortalRequestsController extends Controller
{
    public function __construct(private readonly CustomerRequestGateway $requests) {}

    /**
     * @response array{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->requests->list($this->customerFor($request))]);
    }

    /**
     * @response array<string, mixed>
     */
    public function show(Request $request, string $id): JsonResponse
    {
        return new JsonResponse($this->found($this->requests->show($this->customerFor($request), $id)));
    }

    /**
     * @response array<string, mixed>
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:1', 'max:255'],
            'description' => ['required', 'string', 'min:1', 'max:20000'],
            /*
             * Optional. A customer who does not know which category their
             * problem belongs to should still be able to ask — sorting it is
             * the desk's job, and a required dropdown is where people give up.
             */
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'attachment_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_ids.*' => ['string', 'size:26'],
        ]);

        $account = $request->user('portal');

        return new JsonResponse(
            $this->requests->submit(
                $this->customerFor($request),
                (string) $account?->getKey(),
                (string) $account?->getAttribute('name'),
                (string) $data['subject'],
                (string) $data['description'],
                isset($data['category_id']) ? (int) $data['category_id'] : null,
                $this->attachmentIds($data),
            ),
            201,
        );
    }

    /**
     * @response array<string, mixed>
     */
    public function reply(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:20000'],
            'attachment_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_ids.*' => ['string', 'size:26'],
        ]);

        $account = $request->user('portal');

        return new JsonResponse($this->found($this->requests->reply(
            $this->customerFor($request),
            (string) $account?->getKey(),
            (string) $account?->getAttribute('name'),
            $id,
            (string) $data['body'],
            $this->attachmentIds($data),
        )));
    }

    /**
     * @response array<string, mixed>
     */
    public function reopen(Request $request, string $id): JsonResponse
    {
        $account = $request->user('portal');

        return new JsonResponse($this->found($this->requests->reopen(
            $this->customerFor($request),
            (string) $account?->getKey(),
            (string) $account?->getAttribute('name'),
            $id,
        )));
    }

    /**
     * Turns "not yours" into "not found".
     *
     * 404, never 403. A 403 confirms the id exists, and ULIDs are guessable
     * enough in bulk that confirming would let somebody count this business's
     * customers and requests from outside.
     *
     * @param  array<string, mixed>|null  $result
     * @return array<string, mixed>
     */
    private function found(?array $result): array
    {
        if ($result === null) {
            throw ProblemException::make(
                'portal.request_not_found',
                'Request not found',
                404,
                'No request with that reference belongs to you.',
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function attachmentIds(array $data): array
    {
        /** @var list<string> $ids */
        $ids = $data['attachment_ids'] ?? [];

        return array_values(array_unique($ids));
    }

    private function customerFor(Request $request): string
    {
        $customerId = $request->user('portal')?->getAttribute('customer_id');

        if (! is_string($customerId) || $customerId === '') {
            /*
             * Refused rather than defaulted. An account nobody has linked to a
             * customer has no business opening requests for one, and guessing
             * would attach a stranger's words to somebody's record.
             */
            throw ProblemException::make(
                'portal.account_unlinked',
                'This account is not linked to a customer',
                403,
                'Contact support to finish setting up your account.',
            );
        }

        return $customerId;
    }
}
