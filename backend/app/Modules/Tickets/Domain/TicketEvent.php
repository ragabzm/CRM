<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * One thing that happened to a ticket.
 *
 * Append-only. The history is what an agent reads to understand a ticket they
 * are picking up, and what settles a disagreement about who changed what — an
 * editable history settles nothing.
 */
final class TicketEvent extends Model
{
    use HasUlids;

    protected $table = 'ticket_events';

    protected $guarded = ['*'];

    /** Only `created_at`: an event has no update to record. */
    public $timestamps = false;

    /**
     * Event type constants, so a typo is a fatal error rather than a row
     * nobody can find again.
     */
    public const CREATED = 'ticket.created';

    public const STATUS_CHANGED = 'ticket.status_changed';

    public const PRIORITY_CHANGED = 'ticket.priority_changed';

    public const CATEGORY_CHANGED = 'ticket.category_changed';

    public const ASSIGNEE_CHANGED = 'ticket.assignee_changed';

    public const DEPARTMENT_CHANGED = 'ticket.department_changed';

    public const RESOLVED = 'ticket.resolved';

    public const REOPENED = 'ticket.reopened';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
            'version_after' => 'integer',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Ticket events are append-only. This event has already been written.');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new LogicException('Ticket events are append-only and cannot be deleted.');
    }
}
