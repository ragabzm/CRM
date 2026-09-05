<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** "This note named you." Resolved once, when the note was written. */
final class NoteMention extends Model
{
    use HasUlids;

    protected $table = 'note_mentions';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
