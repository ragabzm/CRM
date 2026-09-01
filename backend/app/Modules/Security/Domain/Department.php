<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A department: a grouping and a filter, nothing more.
 *
 * It does NOT confer visibility. A ticket surfacing outside the caller's
 * department is not a leak — who may see what is decided by role and by the
 * row-level rule in the Tickets module, never by this column. Treating
 * department as a security boundary is how a "filter" quietly becomes an
 * authorization mechanism nobody wrote a test for.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
final class Department extends Model
{
    protected $table = 'departments';

    /** @var list<string> */
    protected $fillable = ['name', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Departments are deactivated, never deleted: a deleted row would orphan
     * the historical `users.department_id` of everyone who ever belonged to it.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
