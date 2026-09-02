<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Shared saved replies.
 *
 * Stored as ONE settings key rather than a table. They are an ordered list of
 * at most a few dozen short strings, always read together, and their order is
 * the whole of their structure — a table would add a join, a sort column to
 * keep contiguous, and migrations, to model an array.
 *
 * They are plain text: no variables, no placeholders, no template engine, no
 * approval step, no versioning. Each of those turns "saved text" into a small
 * programming language that someone then has to debug inside a customer reply.
 */
final class QuickRepliesController extends Controller
{
    public const SETTING_KEY = 'tickets.quick_replies';

    public function __construct(private readonly SettingsRegistry $registry) {}

    /**
     * @response array{data: array<int, array{id:string,label:array{en:string,ar:string},body:array{en:string,ar:string}}>}
     */
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->current()]);
    }

    /**
     * @response array{id:string,label:array{en:string,ar:string},body:array{en:string,ar:string}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $reply = [
            // Server-minted. A client-supplied id could collide with an
            // existing reply and silently overwrite it, or be chosen to
            // collide.
            'id' => (string) Str::ulid(),
            'label' => $validated['label'],
            'body' => $validated['body'],
        ];

        $replies = $this->current();
        $replies[] = $reply;

        $this->persist($replies, $request);

        return new JsonResponse($reply, 201);
    }

    /**
     * @response array{id:string,label:array{en:string,ar:string},body:array{en:string,ar:string}}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $replies = $this->current();
        $index = $this->indexOf($replies, $id);

        $replies[$index] = [
            'id' => $id,
            'label' => $validated['label'],
            'body' => $validated['body'],
        ];

        $this->persist($replies, $request);

        return new JsonResponse($replies[$index]);
    }

    /**
     * @response array{data: array<int, array{id:string,label:array{en:string,ar:string},body:array{en:string,ar:string}}>}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $replies = $this->current();
        $index = $this->indexOf($replies, $id);

        unset($replies[$index]);
        $replies = array_values($replies);

        $this->persist($replies, $request);

        return new JsonResponse(['data' => $replies]);
    }

    /**
     * Reorder by supplying the complete list of ids.
     *
     * @response array{data: array<int, array{id:string,label:array{en:string,ar:string},body:array{en:string,ar:string}}>}
     */
    public function reorder(Request $request): JsonResponse
    {
        $order = $request->input('order');

        if (! is_array($order) || array_filter($order, 'is_string') !== $order) {
            throw ProblemException::make(
                'platform.quick_reply_order_invalid',
                'Order is not a list of ids',
                422,
                'Provide `order` as a list of quick-reply ids.',
            );
        }

        $replies = $this->current();
        $existing = array_column($replies, 'id');

        sort($existing);
        $submitted = $order;
        sort($submitted);

        /*
         * The submitted order must be a permutation of what exists. Accepting a
         * partial list would quietly delete whatever was omitted — a reorder
         * that loses rows is the worst kind of destructive action, because
         * nobody asked to delete anything.
         */
        if ($existing !== $submitted) {
            throw ProblemException::make(
                'platform.quick_reply_order_invalid',
                'Order does not match the saved replies',
                422,
                'The order must list every existing quick reply exactly once.',
            );
        }

        $byId = [];
        foreach ($replies as $reply) {
            $byId[$reply['id']] = $reply;
        }

        $reordered = array_map(static fn (string $id): array => $byId[$id], $order);

        $this->persist($reordered, $request);

        return new JsonResponse(['data' => $reordered]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            // Both languages, always. A reply that exists in one language is a
            // gap an agent discovers mid-conversation with a customer waiting.
            'label.en' => ['required', 'string', 'min:1', 'max:80'],
            'label.ar' => ['required', 'string', 'min:1', 'max:80'],
            'body.en' => ['required', 'string', 'min:1', 'max:5000'],
            'body.ar' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    /**
     * @return list<array{id:string,label:array{en:string,ar:string},body:array{en:string,ar:string}}>
     */
    private function current(): array
    {
        /** @var list<array{id:string,label:array{en:string,ar:string},body:array{en:string,ar:string}}> $replies */
        $replies = $this->registry->get(self::SETTING_KEY);

        return $replies;
    }

    /**
     * @param  list<array{id:string}>  $replies
     */
    private function indexOf(array $replies, string $id): int
    {
        foreach ($replies as $index => $reply) {
            if ($reply['id'] === $id) {
                return $index;
            }
        }

        throw ProblemException::make(
            'platform.quick_reply_not_found',
            'Quick reply not found',
            404,
            "No quick reply with id [{$id}].",
        );
    }

    /**
     * @param  list<array<string, mixed>>  $replies
     */
    private function persist(array $replies, Request $request): void
    {
        $this->registry->set(self::SETTING_KEY, $replies, $request->user()?->getAuthIdentifier());
    }
}
