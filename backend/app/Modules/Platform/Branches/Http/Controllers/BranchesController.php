<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Branches\Domain\Branch;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Branches: create, rename, deactivate. There is no delete.
 *
 * DEACTIVATION NEVER DELETES AND NEVER ORPHANS. A branch that closed still
 * describes where three years of tickets happened; removing the row would
 * either take those tickets with it or leave them pointing at nothing. So a
 * closed branch stops being offered in pickers and goes on labelling every
 * record that already carries it.
 *
 * What is NOT here is the point of the story: no permission, no scope, no data
 * access rule. A branch is a label and a filter, and every method below reads
 * or writes exactly that.
 */
final class BranchesController extends Controller
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function index(Request $request): JsonResponse
    {
        $branches = Branch::query()
            /*
             * Active first, then by name. A picker that buried the branches
             * somebody actually uses under the ones that closed would make the
             * common case the slow one.
             */
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);

        return new JsonResponse(['data' => $branches->map($this->shape(...))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:16', 'alpha_dash', Rule::unique('branches', 'code')],
        ]);

        $branch = new Branch;

        $branch->forceFill([
            'name' => trim((string) $data['name']),
            // Upper-cased on the way in, so `CAI` and `cai` cannot become two
            // branches that read as one.
            'code' => mb_strtoupper(trim((string) $data['code'])),
            'is_active' => true,
        ])->save();

        $this->audit->record(
            AuditAction::BranchCreated,
            targetType: 'branch',
            targetId: (string) $branch->getKey(),
            before: null,
            after: $this->shape($branch),
        );

        return new JsonResponse(['data' => $this->shape($branch)], 201);
    }

    public function update(Request $request, int $branch): JsonResponse
    {
        $row = Branch::query()->whereKey($branch)->first();

        if ($row === null) {
            throw ProblemException::make(
                'platform.branch_not_found',
                'Branch not found',
                404,
                "No branch with id [{$branch}].",
            );
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = $this->shape($row);

        $row->forceFill(array_filter([
            'name' => isset($data['name']) ? trim((string) $data['name']) : null,
        ], static fn (mixed $v): bool => $v !== null))->save();

        if (array_key_exists('is_active', $data)) {
            $row->forceFill(['is_active' => (bool) $data['is_active']])->save();
        }

        $after = $this->shape($row->refresh());

        if ($before !== $after) {
            /*
             * Before and after, both recorded. "The Cairo branch was renamed"
             * is not an answer to "renamed from what?", and that second
             * question is the one asked six months later.
             */
            $this->audit->record(
                AuditAction::BranchUpdated,
                targetType: 'branch',
                targetId: (string) $row->getKey(),
                before: $before,
                after: $after,
            );
        }

        return new JsonResponse(['data' => $after]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Branch $branch): array
    {
        return [
            'id' => (int) $branch->getKey(),
            'name' => (string) $branch->name,
            'code' => (string) $branch->code,
            'is_active' => (bool) $branch->is_active,
        ];
    }
}
