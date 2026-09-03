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
     * Deliberately empty, and deliberately present.
     *
     * An observer or a model event on this table would be a second, invisible
     * writer of history — the one place where "something else also changed the
     * row" must be impossible. Declared so that adding one is a visible edit to
     * this file rather than a new file nobody reviews, and `TicketEventsAppendOnlyTest`
     * fails if a `TicketEvent::observe(` ever appears.
     */
    protected static function booted(): void {}

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

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        /*
         * Every door, not just the front one. `save()` alone left `update()`,
         * `updateOrFail()` and `forceDelete()` reaching the database directly —
         * and an append-only store that can be edited through three of its four
         * methods is not append-only, it is a convention.
         */
        throw new LogicException('Ticket events are append-only and cannot be updated.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function updateOrFail(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Ticket events are append-only and cannot be updated.');
    }

    public function delete(): bool
    {
        throw new LogicException('Ticket events are append-only and cannot be deleted.');
    }

    public function forceDelete(): bool
    {
        throw new LogicException('Ticket events are append-only and cannot be deleted.');
    }
}
