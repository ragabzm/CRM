<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way to reach a customer.
 *
 * @property string $kind
 * @property string $value
 * @property string $value_normalised
 * @property bool $is_primary
 */
final class ContactIdentifier extends Model
{
    use HasUlids;

    protected $table = 'contact_identifiers';

    protected $fillable = ['customer_id', 'kind', 'value', 'value_normalised', 'is_primary'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /**
     * Uppercase, matching Customer and every other ULID the product mints.
     *
     * HasUlids defaults to lowercase; Str::ulid() produces the canonical
     * Crockford uppercase form. Two casings in one datastore is a trap for
     * anyone comparing ids by hand.
     */
    public function newUniqueId(): string
    {
        return (string) \Illuminate\Support\Str::ulid();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function kind(): ContactKind
    {
        return ContactKind::from($this->kind);
    }

    /**
     * Builds the persisted shape from what someone typed.
     *
     * Normalisation happens HERE rather than at each call site, so a record
     * created by the API, by a future import and by a console command are all
     * comparable to one another.
     *
     * @return array{kind:string,value:string,value_normalised:string,is_primary:bool}
     */
    public static function shapeFor(ContactKind $kind, string $value, bool $isPrimary = false): array
    {
        return [
            'kind' => $kind->value,
            // Trimmed but otherwise untouched: this is what we show back and
            // what someone dials.
            'value' => trim($value),
            'value_normalised' => IdentifierNormaliser::normalise($kind, $value),
            'is_primary' => $isPrimary,
        ];
    }
}
