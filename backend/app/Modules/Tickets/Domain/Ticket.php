<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A ticket.
 *
 * `$guarded = ['*']` — nothing is mass-assignable, ever. Every mutation goes
 * through a command in Domain/Commands, which uses forceFill deliberately. A
 * fillable list here would be a second, quieter write path that skips the
 * version guard and writes no event.
 *
 * @property string $reference
 * @property int $version
 * @property string $status
 * @property string $priority
 */
final class Ticket extends Model
{
    use HasUlids;

    protected $table = 'tickets';

    /** Nothing may be mass-assigned. Commands forceFill. */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => Priority::class,
            'channel' => TicketChannel::class,
            'version' => 'integer',

            /*
             * Cast, or the lifecycle rules do date arithmetic on strings. The
             * reopen window compares `closed_at` against now(); without these
             * the comparison is a fatal error rather than a wrong answer,
             * which is at least loud.
             */
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'last_customer_activity_at' => 'immutable_datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
