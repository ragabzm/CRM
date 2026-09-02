<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain;

use App\Modules\Security\Contracts\DepartmentDirectory;

final class EloquentDepartmentDirectory implements DepartmentDirectory
{
    public function exists(int $id): bool
    {
        return Department::query()->whereKey($id)->exists();
    }

    public function name(int $id): ?string
    {
        $name = Department::query()->whereKey($id)->value('name');

        return is_string($name) ? $name : null;
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        /** @var array<int, string> $options */
        $options = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $options;
    }
}
