<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http;

use App\Models\User;

/**
 * The questions the assignment rules ask about a person.
 *
 * Sits in Tickets and reads the shared `users` table rather than reaching into
 * the Security module's models — `users` is Laravel's own table, not Security's
 * private storage, and NoCrossModuleModelsTest draws the line at module models.
 */
final class AssigneeDirectory
{
    public function isAssignable(int $userId): bool
    {
        $user = User::query()->find($userId);

        // A deactivated account cannot work a ticket, and assigning to one
        // parks the work somewhere nobody is looking.
        return $user !== null && $user->isActive();
    }

    public function belongsToDepartment(int $userId, ?int $departmentId): bool
    {
        if ($departmentId === null) {
            // A ticket with no department constrains nobody.
            return true;
        }

        $userDepartment = User::query()->whereKey($userId)->value('department_id');

        // A user with no department is assignable anywhere: they are not
        // excluded from a team, they are simply not in one.
        return $userDepartment === null || (int) $userDepartment === $departmentId;
    }

    /**
     * Display names for a whole page of ids, in one query.
     *
     * Taking the list rather than one id at a time is the point: a ticket
     * history is mostly the same few people repeated, and resolving names row
     * by row turns a 200-event page into 200 queries.
     *
     * @param  list<string>  $userIds
     * @return array<string, string>  id => name, missing for accounts that are gone
     */
    public function namesFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (string $name, mixed $id): array => [(string) $id => $name])
            ->all();
    }

    public function departmentOf(int $userId): ?int
    {
        $value = User::query()->whereKey($userId)->value('department_id');

        return $value === null ? null : (int) $value;
    }
}
