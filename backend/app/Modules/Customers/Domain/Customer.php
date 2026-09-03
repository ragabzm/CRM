<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The customer record.
 *
 * @property string $reference
 * @property string $full_name
 * @property string $state
 */
final class Customer extends Model
{
    use HasUlids;

    protected $table = 'customers';

    protected $fillable = [
        'reference', 'full_name', 'department_id', 'state', 'preferred_channel', 'preferred_locale', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['deactivated_at' => 'immutable_datetime'];
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(ContactIdentifier::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('state', CustomerState::Active->value);
    }

    public function state(): CustomerState
    {
        return CustomerState::from($this->state);
    }

    /**
     * A short, human-quotable reference.
     *
     * Base32 without the letters and digits that get misheard or misread — no
     * I, L, O, U, 0 or 1 — because the first thing this string does is get read
     * aloud over a phone line.
     */
    public const REFERENCE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function mintReference(): string
    {
        $body = '';

        for ($i = 0; $i < 8; $i++) {
            $body .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
        }

        return 'C-'.$body;
    }

    /**
     * A ULID, not an auto-increment.
     *
     * Sequential ids leak how many customers exist and how fast they arrive,
     * which is business information that appears in every URL an agent might
     * paste anywhere.
     */
    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }
}
