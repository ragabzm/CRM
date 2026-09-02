<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Something a colleague wrote about a customer.
 *
 * The author's name is stored alongside their id, so a note still says who
 * wrote it after that person leaves and their account is removed. A join would
 * lose the name at exactly the moment someone is trying to work out who knew
 * what.
 *
 * @property string $body
 * @property string $author_name
 */
final class CustomerNote extends Model
{
    use HasUlids;

    protected $table = 'customer_notes';

    protected $fillable = ['customer_id', 'author_id', 'author_name', 'body'];

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function wasAuthoredBy(int|string|null $userId): bool
    {
        if ($userId === null || $this->author_id === null) {
            return false;
        }

        // Compared as strings: the column is an integer and the caller may hold
        // it either way.
        return (string) $this->author_id === (string) $userId;
    }
}
