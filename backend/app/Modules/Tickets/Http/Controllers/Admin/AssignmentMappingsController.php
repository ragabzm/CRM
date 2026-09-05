<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Audit\AuditLogger;
use App\Modules\Tickets\Domain\Assignment\AssignmentMapping;
use App\Modules\Tickets\Domain\Assignment\MappingSource;
use App\Modules\Tickets\Domain\Assignment\MappingTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The whole editor: a source, a target, and nothing else.
 *
 * There is no condition, no operator, no ordering control and no
 * enable/disable switch, and there is nowhere for one to go — the unique index
 * on `(source_type, source_id)` means every list this returns is already
 * unambiguous.
 *
 * The one thing it adds beyond CRUD is telling the truth about a row that
 * cannot fire: a mapping whose target has been deactivated is listed with
 * `active: false` and a reason, rather than sitting there looking like it
 * works. It is skipped at creation and existing tickets are untouched.
 */
final class AssignmentMappingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @response array{data: array<int, array<string, mixed>>, precedence: list<string>}
     */
    public function index(): JsonResponse
    {
        $mappings = AssignmentMapping::query()->orderBy('source_type')->orderBy('source_id')->get();

        return new JsonResponse([
            'data' => $mappings->map(fn (AssignmentMapping $m) => $this->shape($m))->all(),

            /*
             * Stated, not left to be discovered.
             *
             * The story requires the editor to say that a category mapping
             * beats a department one, and this is where the interface reads it
             * from rather than hard-coding the same sentence a second time.
             */
            'precedence' => array_map(
                static fn (MappingSource $s): string => $s->value,
                MappingSource::inPrecedenceOrder(),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $this->assertSourceExists($validated);
        $this->assertTargetExists($validated);

        if (AssignmentMapping::query()
            ->where('source_type', $validated['source_type'])
            ->where('source_id', $validated['source_id'])
            ->exists()
        ) {
            /*
             * Refused, not silently replaced. Two rows for one source would
             * need ordering to resolve, and the point of this table is that
             * there is nothing to resolve — so the administrator is told the
             * mapping already exists and can edit it.
             */
            throw ProblemException::make(
                'tickets.mapping_exists',
                'That source already has a mapping',
                409,
                'A category or a department maps to exactly one target. Edit the existing row instead.',
                ['source_type' => $validated['source_type'], 'source_id' => $validated['source_id']],
            );
        }

        $mapping = AssignmentMapping::create($validated);

        $this->audit->write(
            $this->actorId($request),
            'config.changed',
            'assignment_mapping',
            (string) $mapping->getKey(),
            [],
            $validated,
        );

        return new JsonResponse($this->shape($mapping), 201);
    }

    public function update(Request $request, AssignmentMapping $mapping): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', Rule::in(MappingTarget::values())],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->assertTargetExists($validated);

        $before = ['target_type' => $mapping->target_type->value, 'target_id' => $mapping->target_id];

        $mapping->fill($validated)->save();

        $this->audit->write(
            $this->actorId($request),
            'config.changed',
            'assignment_mapping',
            (string) $mapping->getKey(),
            $before,
            $validated,
        );

        return new JsonResponse($this->shape($mapping->refresh()));
    }

    public function destroy(Request $request, AssignmentMapping $mapping): JsonResponse
    {
        $before = [
            'source_type' => $mapping->source_type->value,
            'source_id' => $mapping->source_id,
            'target_type' => $mapping->target_type->value,
            'target_id' => $mapping->target_id,
        ];

        $mapping->delete();

        $this->audit->write(
            $this->actorId($request),
            'config.changed',
            'assignment_mapping',
            (string) $mapping->getKey(),
            $before,
            [],
        );

        // Existing tickets are untouched. A mapping decides where NEW work
        // lands; rewriting history because a rule changed would move tickets
        // out from under the people already working them.
        return new JsonResponse(['deleted' => (int) $mapping->getKey()]);
    }

    private function actorId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in(MappingSource::values())],
            'source_id' => ['required', 'integer', 'min:1'],
            'target_type' => ['required', Rule::in(MappingTarget::values())],
            'target_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertSourceExists(array $validated): void
    {
        $table = $validated['source_type'] === MappingSource::Category->value
            ? 'ticket_categories'
            : 'departments';

        if (DB::table($table)->where('id', $validated['source_id'])->exists()) {
            return;
        }

        throw ProblemException::make(
            'tickets.mapping_source_unknown',
            'That source does not exist',
            422,
            'Choose a category or a department from the list.',
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertTargetExists(array $validated): void
    {
        $table = $validated['target_type'] === MappingTarget::Agent->value ? 'users' : 'departments';

        if (DB::table($table)->where('id', $validated['target_id'])->exists()) {
            return;
        }

        throw ProblemException::make(
            'tickets.mapping_target_unknown',
            'That target does not exist',
            422,
            'Choose an agent or a department from the list.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(AssignmentMapping $mapping): array
    {
        $inactive = $this->inactiveReason($mapping);

        return [
            'id' => (int) $mapping->getKey(),
            'source_type' => $mapping->source_type->value,
            'source_id' => $mapping->source_id,
            'target_type' => $mapping->target_type->value,
            'target_id' => $mapping->target_id,

            /*
             * Whether this row can actually fire, and why not.
             *
             * A mapping pointing at somebody who left is worse than no mapping
             * at all: it looks like the queue is being sorted while every
             * matching ticket quietly stays unassigned. Saying so on the row
             * is the difference between a rule and a mystery.
             */
            'active' => $inactive === null,
            'inactive_reason' => $inactive,
        ];
    }

    private function inactiveReason(AssignmentMapping $mapping): ?string
    {
        if ($mapping->target_type === MappingTarget::Agent) {
            $active = DB::table('users')->where('id', $mapping->target_id)->value('is_active');

            return $active === null
                ? 'That account no longer exists.'
                : ((bool) $active ? null : 'That account has been deactivated.');
        }

        $active = DB::table('departments')->where('id', $mapping->target_id)->value('is_active');

        return $active === null
            ? 'That department no longer exists.'
            : ((bool) $active ? null : 'That department has been deactivated.');
    }
}
