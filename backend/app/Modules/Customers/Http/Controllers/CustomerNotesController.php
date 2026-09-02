<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\CustomerNote;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Domain\Capabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notes on a customer.
 *
 * Reading and writing a note need only customer READ access: noting what a
 * caller said is part of handling the call, and an agent who could see a
 * customer but not record anything about them would keep that knowledge in
 * their own head.
 *
 * Editing and deleting are narrower, and they are about AUTHORSHIP rather than
 * role. You may change what you wrote; you may not rewrite what a colleague
 * wrote, because a note is a record of what somebody said and silently
 * editing it destroys that. A supervisor may DELETE anything — someone has to
 * be able to remove a note written in anger or containing a customer's card
 * number — but not edit it, because a deletion is visible and an edit is not.
 */
final class CustomerNotesController extends Controller
{
    public const MAX_BODY_LENGTH = 5000;

    /**
     * @response array{data: array<int, array<string, mixed>>}
     */
    public function index(string $customerId): JsonResponse
    {
        $this->findCustomer($customerId);

        $notes = CustomerNote::query()
            ->where('customer_id', $customerId)
            // Newest first, with the ulid as a tiebreak so two notes written in
            // the same second keep a stable order across pages.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return new JsonResponse([
            'data' => $notes->map(fn (CustomerNote $note) => $this->shape($note))->all(),
        ]);
    }

    /**
     * @response array<string, mixed>
     */
    public function store(Request $request, string $customerId): JsonResponse
    {
        $this->findCustomer($customerId);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:'.self::MAX_BODY_LENGTH],
        ]);

        $user = $request->user();

        $note = CustomerNote::query()->create([
            'customer_id' => $customerId,
            'author_id' => $user?->getAuthIdentifier(),
            // Denormalised at write time so the note survives the account.
            'author_name' => (string) ($user?->getAttribute('name') ?? 'Unknown'),
            'body' => trim((string) $validated['body']),
        ]);

        return new JsonResponse($this->shape($note), 201);
    }

    /**
     * @response array<string, mixed>
     */
    public function update(Request $request, string $noteId): JsonResponse
    {
        $note = $this->findNote($noteId);
        $user = $request->user();

        if (! $note->wasAuthoredBy($user?->getAuthIdentifier())) {
            // Not even a supervisor. Rewriting what a colleague said, in their
            // name, leaves no trace that it happened.
            throw ProblemException::make(
                'customers.note_not_yours',
                'You can only edit your own notes',
                403,
                'This note was written by someone else. You can add your own note instead.',
            );
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:'.self::MAX_BODY_LENGTH],
        ]);

        $note->fill(['body' => trim((string) $validated['body'])])->save();

        return new JsonResponse($this->shape($note->refresh()));
    }

    public function destroy(Request $request, string $noteId): JsonResponse
    {
        $note = $this->findNote($noteId);
        $user = $request->user();

        $isAuthor = $note->wasAuthoredBy($user?->getAuthIdentifier());
        // A supervisor can remove anything: someone has to be able to take down
        // a note written in anger, or one holding a card number.
        $canModerate = $user?->can(Capabilities::CUSTOMER_MANAGE) ?? false;

        if (! $isAuthor && ! $canModerate) {
            throw ProblemException::make(
                'customers.note_not_yours',
                'You can only delete your own notes',
                403,
                'This note was written by someone else. Ask a supervisor if it needs removing.',
            );
        }

        $note->delete();

        return new JsonResponse(['deleted' => $noteId]);
    }

    private function findCustomer(string $id): Customer
    {
        $customer = Customer::query()->find($id);

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

    private function findNote(string $id): CustomerNote
    {
        $note = CustomerNote::query()->find($id);

        if ($note === null) {
            throw ProblemException::make(
                'customers.note_not_found',
                'Note not found',
                404,
                "No note with id [{$id}].",
            );
        }

        return $note;
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CustomerNote $note): array
    {
        return [
            'id' => (string) $note->getKey(),
            'customer_id' => (string) $note->customer_id,
            'author' => [
                'id' => $note->author_id !== null ? (string) $note->author_id : null,
                'name' => $note->author_name,
            ],
            'body' => $note->body,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
            // So the UI can decide what to offer without re-deriving the rule.
            'edited' => $note->created_at?->ne($note->updated_at) ?? false,
        ];
    }
}
