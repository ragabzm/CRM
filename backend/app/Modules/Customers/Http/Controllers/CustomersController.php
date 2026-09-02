<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Domain\ContactIdentifier;
use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\CustomerSearch;
use App\Modules\Customers\Domain\CustomerSearchCriteria;
use App\Modules\Customers\Domain\CustomerState;
use App\Modules\Customers\Domain\DuplicateDetector;
use App\Modules\Customers\Http\Requests\StoreCustomerRequest;
use App\Modules\Customers\Http\Requests\UpdateCustomerRequest;
use App\Modules\Customers\Http\Resources\CustomerResource;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Contracts\DepartmentDirectory;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class CustomersController extends Controller
{
    /** How many times to retry a reference collision before giving up. */
    private const REFERENCE_ATTEMPTS = 5;

    public function __construct(
        private readonly CustomerSearch $search,
        private readonly DuplicateDetector $duplicates,
        private readonly DepartmentDirectory $departments,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @response array{data: array<int, array<string, mixed>>, meta: array{page:int,per_page:int,total:int,last_page:int}}
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'string', 'max:200'],
            'state' => ['sometimes', 'in:active,inactive,all'],
            'department_id' => ['sometimes', 'integer'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.CustomerSearch::MAX_LIMIT],
        ]);

        $page = $this->search->paginate(CustomerSearchCriteria::fromValidated($validated));
        $names = $this->departments->options();

        return new JsonResponse([
            'data' => array_map(
                fn (Customer $customer): array => CustomerResource::summary(
                    $customer,
                    $names[(int) $customer->department_id] ?? $this->departments->name((int) $customer->department_id),
                ),
                $page->items(),
            ),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * A single customer, whatever its state.
     *
     * Deactivated records resolve here on purpose. They are absent from search
     * so they do not clutter today's work, but a link in an old ticket must
     * still open the person it refers to — a 404 there would look like data
     * loss.
     *
     * @response array<string, mixed>
     */
    public function show(string $id): JsonResponse
    {
        $customer = $this->find($id);

        return new JsonResponse(CustomerResource::detail(
            $customer,
            $this->departments->name((int) $customer->department_id),
        ));
    }

    /**
     * @response array<string, mixed>
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $identifiers = $this->identifiersFrom($data['identifiers']);

        if ($request->boolean('confirm_create_duplicate') !== true) {
            $matches = $this->duplicates->preview(
                $this->valuesOfKind($identifiers, ContactKind::Email),
                $this->valuesOfKind($identifiers, ContactKind::Phone),
            );

            if ($matches !== []) {
                /*
                 * An OFFER, not a refusal. The 409 carries the matches so the
                 * form can show who already exists; resubmitting with
                 * `confirm_create_duplicate` creates the record, because two
                 * people in a household really do share a phone number.
                 */
                throw ProblemException::make(
                    'customers.duplicate_offer',
                    'This person may already exist',
                    409,
                    'One or more of these contact details already belong to a customer. Open the existing record, or confirm to create a second one.',
                    ['matches' => $matches],
                );
            }
        }

        $customer = DB::transaction(function () use ($data, $identifiers): Customer {
            $customer = $this->insertWithReference($data);

            foreach ($identifiers as $identifier) {
                $customer->identifiers()->create($identifier);
            }

            $this->audit->record(
                action: AuditAction::CustomerFieldChanged,
                targetType: 'customer',
                targetId: (string) $customer->getKey(),
                after: ['full_name' => $customer->full_name, 'reference' => $customer->reference],
            );

            return $customer;
        });

        return new JsonResponse(
            CustomerResource::detail(
                $customer->load('identifiers'),
                $this->departments->name((int) $customer->department_id),
            ),
            201,
        );
    }

    /**
     * @response array<string, mixed>
     */
    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        $customer = $this->find($id);
        $data = $request->validated();

        DB::transaction(function () use ($customer, $data): void {
            $before = [
                'full_name' => $customer->full_name,
                'department_id' => $customer->department_id,
                'preferred_channel' => $customer->preferred_channel,
            ];

            $customer->fill(array_intersect_key(
                $data,
                array_flip(['full_name', 'department_id', 'preferred_channel', 'notes']),
            ))->save();

            if (isset($data['identifiers'])) {
                $this->replaceIdentifiers($customer, $this->identifiersFrom($data['identifiers']));
            }

            $this->audit->record(
                action: AuditAction::CustomerFieldChanged,
                targetType: 'customer',
                targetId: (string) $customer->getKey(),
                before: $before,
                after: [
                    'full_name' => $customer->full_name,
                    'department_id' => $customer->department_id,
                    'preferred_channel' => $customer->preferred_channel,
                ],
            );
        });

        return new JsonResponse(CustomerResource::detail(
            $customer->refresh()->load('identifiers'),
            $this->departments->name((int) $customer->department_id),
        ));
    }

    /**
     * Deactivate. The row survives, and so does everything attached to it.
     *
     * @response array<string, mixed>
     */
    public function deactivate(string $id): JsonResponse
    {
        return $this->setState($id, CustomerState::Inactive);
    }

    /**
     * @response array<string, mixed>
     */
    public function reactivate(string $id): JsonResponse
    {
        return $this->setState($id, CustomerState::Active);
    }

    private function setState(string $id, CustomerState $state): JsonResponse
    {
        $customer = $this->find($id);

        // Idempotent: asking for a state the record already holds is not an
        // error, it is a request that has already been satisfied.
        if ($customer->state !== $state->value) {
            $before = ['state' => $customer->state];

            $customer->forceFill([
                'state' => $state->value,
                'deactivated_at' => $state === CustomerState::Inactive ? now() : null,
            ])->save();

            $this->audit->record(
                action: AuditAction::CustomerFieldChanged,
                targetType: 'customer',
                targetId: (string) $customer->getKey(),
                before: $before,
                after: ['state' => $state->value],
            );
        }

        return new JsonResponse(CustomerResource::detail(
            $customer->refresh()->load('identifiers'),
            $this->departments->name((int) $customer->department_id),
        ));
    }

    private function find(string $id): Customer
    {
        $customer = Customer::query()->with('identifiers')->find($id);

        if ($customer === null) {
            throw ProblemException::make(
                'customers.not_found',
                'Customer not found',
                404,
                "No customer with id [{$id}].",
            );
        }

        return $customer;
    }

    /**
     * Insert, retrying on the vanishingly unlikely reference collision.
     *
     * @param  array<string, mixed>  $data
     */
    private function insertWithReference(array $data): Customer
    {
        for ($attempt = 1; $attempt <= self::REFERENCE_ATTEMPTS; $attempt++) {
            try {
                return Customer::create([
                    'reference' => Customer::mintReference(),
                    'full_name' => trim((string) $data['full_name']),
                    'department_id' => (int) $data['department_id'],
                    'state' => CustomerState::Active->value,
                    'preferred_channel' => $data['preferred_channel'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
            } catch (QueryException $e) {
                // Only a reference collision is worth retrying; anything else
                // is a real failure and must surface.
                if ($attempt === self::REFERENCE_ATTEMPTS || ! str_contains($e->getMessage(), 'reference')) {
                    throw $e;
                }
            }
        }

        throw ProblemException::make(
            'customers.reference_unavailable',
            'Could not allocate a customer reference',
            503,
            'Please try again.',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $identifiers
     */
    private function replaceIdentifiers(Customer $customer, array $identifiers): void
    {
        /*
         * Replaced wholesale, inside the transaction. The agent is looking at
         * the complete list, so the rows they removed are absent from what they
         * submitted — merging would silently bring them back.
         */
        $customer->identifiers()->delete();

        foreach ($identifiers as $identifier) {
            $customer->identifiers()->create($identifier);
        }
    }

    /**
     * @param  array<int, mixed>  $input
     * @return list<array{kind:string,value:string,value_normalised:string,is_primary:bool}>
     */
    private function identifiersFrom(array $input): array
    {
        $shaped = [];
        $seen = [];

        foreach ($input as $index => $identifier) {
            $kind = ContactKind::from((string) $identifier['kind']);
            $shape = ContactIdentifier::shapeFor(
                $kind,
                (string) $identifier['value'],
                (bool) ($identifier['is_primary'] ?? false),
            );

            if ($shape['value_normalised'] === '') {
                throw ProblemException::make(
                    'customers.identifier_invalid',
                    'Contact detail is not usable',
                    422,
                    'That value contains nothing we can contact the customer on.',
                    ['pointer' => "identifiers.{$index}.value"],
                );
            }

            $key = $shape['kind'].':'.$shape['value_normalised'];

            if (isset($seen[$key])) {
                /*
                 * Caught here rather than left to the unique index, so the
                 * message names WHICH row is the repeat. A database integrity
                 * error would surface as a 500 with nothing the form can
                 * highlight.
                 */
                throw ProblemException::make(
                    'customers.identifier_duplicated',
                    'That contact detail is listed twice',
                    422,
                    'Each email address and phone number can appear once per customer.',
                    ['pointer' => "identifiers.{$index}.value"],
                );
            }

            $seen[$key] = true;
            $shaped[] = $shape;
        }

        return $shaped;
    }

    /**
     * @param  list<array{kind:string,value:string}>  $identifiers
     * @return list<string>
     */
    private function valuesOfKind(array $identifiers, ContactKind $kind): array
    {
        return array_values(array_map(
            static fn (array $identifier): string => $identifier['value'],
            array_filter($identifiers, static fn (array $i): bool => $i['kind'] === $kind->value),
        ));
    }
}
