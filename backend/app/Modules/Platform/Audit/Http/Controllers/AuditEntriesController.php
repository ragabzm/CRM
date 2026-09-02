<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Audit\Application\AuditFilter;
use App\Modules\Platform\Audit\Application\AuditQuery;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Audit\Domain\AuditEntry;
use App\Modules\Platform\Audit\Http\Requests\AuditListRequest;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;

/**
 * Reading the audit log. There is no other verb.
 *
 * `store`, `update` and `destroy` are absent rather than present-and-refusing:
 * a method that exists to return 403 is a method someone can be talked into
 * relaxing. With none registered, the router answers 405 and there is nothing
 * to relax.
 */
final class AuditEntriesController extends Controller
{
    public function __construct(private readonly AuditQuery $query) {}

    /**
     * @response array{data: array<int, array{id:string,occurred_at:string,actor:array{id:string|null,type:string,label:string},action:string,target:array{type:string|null,id:string|null},source_ip:string|null,request_id:string|null}>, meta: array{page:int,per_page:int,total:int,last_page:int}, actions: array<int, string>}
     */
    public function index(AuditListRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $page = $this->query->paginate(
            AuditFilter::fromValidated($validated),
            isset($validated['per_page']) ? (int) $validated['per_page'] : 25,
        );

        return new JsonResponse([
            'data' => array_map(
                fn (AuditEntry $entry): array => $this->shape($entry),
                $page->items(),
            ),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            // The filter's vocabulary travels with the data, so the console
            // builds its action dropdown from what the server actually records
            // rather than from a second list that drifts.
            'actions' => AuditAction::values(),
        ]);
    }

    /**
     * @response array{id:string,occurred_at:string,actor:array{id:string|null,type:string,label:string},action:string,target:array{type:string|null,id:string|null},before:array<string,mixed>|null,after:array<string,mixed>|null,source_ip:string|null,request_id:string|null}
     */
    public function show(string $id): JsonResponse
    {
        $entry = $this->query->find($id);

        if ($entry === null) {
            throw ProblemException::make(
                'platform.audit_entry_not_found',
                'Audit entry not found',
                404,
                "No audit entry with id [{$id}].",
            );
        }

        // The detail view is the only place before/after are sent: a list of
        // fifty entries each carrying two JSON documents is a slow page whose
        // payload nobody reads.
        return new JsonResponse($this->shape($entry, withPayloads: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(AuditEntry $entry, bool $withPayloads = false): array
    {
        $shape = [
            'id' => (string) $entry->getKey(),
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'actor' => [
                'id' => $entry->actor_id !== null ? (string) $entry->actor_id : null,
                'type' => (string) $entry->actor_type,
                'label' => (string) $entry->actor_label,
            ],
            'action' => (string) $entry->action,
            'target' => [
                'type' => $entry->target_type !== null ? (string) $entry->target_type : null,
                'id' => $entry->target_id !== null ? (string) $entry->target_id : null,
            ],
            'source_ip' => $entry->source_ip !== null ? (string) $entry->source_ip : null,
            'request_id' => $entry->request_id !== null ? (string) $entry->request_id : null,
        ];

        if ($withPayloads) {
            $shape['before'] = $entry->before;
            $shape['after'] = $entry->after;
        }

        return $shape;
    }
}
