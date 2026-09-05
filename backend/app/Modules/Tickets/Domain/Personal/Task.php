<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A note to self with a tick box.
 *
 * Nothing may be mass-assigned; the commands `forceFill`, the same rule the
 * ticket aggregate follows. A task is small enough that guarding it looks like
 * ceremony, and that is exactly when a stray `update($request->all())` gets
 * written — here it would let somebody set `user_id` and hand themselves
 * a colleague's task.
 */
final class Task extends Model
{
    use HasUlids;

    protected $table = 'tasks';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Past its due date and still open.
     *
     * Overdue is DERIVED, never stored. A stored flag is wrong from the minute
     * after it is written, and the row that never gets swept is the one that
     * silently stops being flagged.
     */
    public function isOverdue(): bool
    {
        return $this->completed_at === null
            && $this->due_at !== null
            && $this->due_at->isPast();
    }
}
