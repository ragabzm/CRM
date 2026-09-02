<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Domain;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One recorded action. Readable, never editable.
 *
 * The immutability is enforced three times over, because each layer covers a
 * hole the others leave:
 *
 *   1 The TABLE has no `updated_at` and no soft-delete column — there is
 *     nowhere for a mutation to record itself.
 *   2 This MODEL throws on any save after the first insert and on any delete,
 *     so code that reaches for the obvious Eloquent verb fails loudly at the
 *     call site rather than quietly succeeding.
 *   3 The HTTP surface registers only GET, so the router answers 405 to a
 *     write. There is no controller method to reach.
 *
 * Belt, braces and a second belt is proportionate here: an audit log that can
 * be edited is not merely a weaker audit log, it is worthless — the one thing
 * it is for is being trustworthy after the fact.
 *
 * The writer does not use this model at all. It inserts through the query
 * builder, so not even a future accidental `saving` hook can rewrite an entry
 * on its way in.
 */
final class AuditEntry extends Model
{
    protected $table = 'audit_entries';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * No timestamps: `created_at`/`updated_at` are Eloquent's mutation record,
     * and there is no mutation to record. `occurred_at` is the only time this
     * row has.
     */
    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        /*
         * `exists` rather than `wasRecentlyCreated`: a model hydrated from a
         * query has `wasRecentlyCreated === false` too, and that is exactly the
         * instance someone would try to edit.
         */
        if ($this->exists) {
            throw new LogicException(
                'Audit entries are append-only. This entry has already been written and cannot be changed.',
            );
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new LogicException(
            'Audit entries are append-only. Deleting one would destroy the record it exists to keep.',
        );
    }

    /**
     * Blocked separately from `save()`: `update()` on an Eloquent model routes
     * through `save()`, but on a QUERY it does not — and this method being
     * present on the model is what makes `$entry->update([...])` look legal.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Audit entries are append-only and cannot be updated.');
    }
}
