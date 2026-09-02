<?php

declare(strict_types=1);

namespace App\Modules\Security\Contracts;

/**
 * Reading the department list, for modules that group by it.
 *
 * Published by Security because Security owns the table. Higher tiers depend on
 * this interface rather than on the Eloquent model, so a change to how a
 * department is stored is Security's business alone — which is the difference
 * between one shared concept and two tables that drift apart.
 */
interface DepartmentDirectory
{
    public function exists(int $id): bool;

    /** Null when there is no such department. */
    public function name(int $id): ?string;

    /**
     * Active departments, id => name, for filters and pickers.
     *
     * @return array<int, string>
     */
    public function options(): array;
}
