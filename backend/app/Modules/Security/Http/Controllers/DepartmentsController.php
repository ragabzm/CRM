<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Contracts\DepartmentUsageProbe;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Http\Requests\StoreDepartmentRequest;
use App\Modules\Security\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Departments: create, rename, deactivate. No delete.
 */
final class DepartmentsController extends Controller
{
    public function __construct(
        // Answered by the Tickets module at runtime; Security declares the
        // interface and never imports a ticket model.
        private readonly DepartmentUsageProbe $usage,
    ) {}

    /**
     * @response array{data: array<int, array{id:int,name:string,is_active:bool}>}
     */
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'data' => Department::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Department $d) => $this->shape($d))
                ->all(),
        ]);
    }

    /**
     * @response array{id:int,name:string,is_active:bool}
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create([
            'name' => trim((string) $request->validated('name')),
            'is_active' => true,
        ]);

        return new JsonResponse($this->shape($department), 201);
    }

    /**
     * Rename.
     *
     * @response array{id:int,name:string,is_active:bool}
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->fill(['name' => trim((string) $request->validated('name'))])->save();

        return new JsonResponse($this->shape($department->refresh()));
    }

    /**
     * Deactivate — refused while active tickets remain.
     *
     * @response array{id:int,name:string,is_active:bool}
     */
    public function deactivate(Department $department): JsonResponse
    {
        DB::transaction(function () use ($department): void {
            /*
             * Lock the row for the duration of the check-and-write. Without it,
             * two concurrent deactivations both read a count of zero and both
             * proceed. The remaining race — a ticket created after the count —
             * is left to Story 4.1, which must refuse new tickets into an
             * inactive department.
             */
            Department::query()->whereKey($department->getKey())->lockForUpdate()->first();

            $active = $this->usage->activeTicketCount((int) $department->getKey());

            if ($active > 0) {
                /*
                 * Refused, not confirmed away. A confirmation dialog here would
                 * let someone strand real work with one click and no way to
                 * find it again; the refusal carries the COUNT and a PATH so
                 * the reader can go and deal with the tickets instead of
                 * guessing how many there are.
                 */
                throw ProblemException::make(
                    'security.department_has_active_tickets',
                    'Department has active tickets',
                    409,
                    __('authorization.department_has_active_tickets', ['count' => $active]),
                    [
                        'activeTicketCount' => $active,
                        'ticketsPath' => '/tickets?department='.$department->getKey().'&status=open',
                    ],
                );
            }

            $department->forceFill(['is_active' => false])->save();
        });

        return new JsonResponse($this->shape($department->refresh()));
    }

    /**
     * @return array{id:int,name:string,is_active:bool}
     */
    private function shape(Department $department): array
    {
        return [
            'id' => (int) $department->getKey(),
            'name' => (string) $department->name,
            'is_active' => (bool) $department->is_active,
        ];
    }
}
