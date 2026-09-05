<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Assignment;

use Illuminate\Database\Eloquent\Model;

/**
 * One sentence: this category, or this department, goes to that agent, or that
 * department.
 *
 * @property int $id
 * @property MappingSource $source_type
 * @property int $source_id
 * @property MappingTarget $target_type
 * @property int $target_id
 */
final class AssignmentMapping extends Model
{
    protected $table = 'assignment_mappings';

    /** @var list<string> */
    protected $fillable = ['source_type', 'source_id', 'target_type', 'target_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => MappingSource::class,
            'target_type' => MappingTarget::class,
            'source_id' => 'integer',
            'target_id' => 'integer',
        ];
    }
}
