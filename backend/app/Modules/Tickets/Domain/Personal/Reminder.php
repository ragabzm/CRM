<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** One moment, one owner, one thing to come back to. */
final class Reminder extends Model
{
    use HasUlids;

    protected $table = 'reminders';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'remind_at' => 'immutable_datetime',
            'fired_at' => 'immutable_datetime',
        ];
    }
}
