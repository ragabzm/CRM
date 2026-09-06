<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branches\Domain;

use Illuminate\Database\Eloquent\Model;

/**
 * An office, as a label.
 *
 * NO GLOBAL SCOPE, here or anywhere. That absence is the mechanism, not an
 * omission: a scope registered "just for filtering" is how a label becomes an
 * access boundary, and the first request it silently narrows is one nobody
 * notices until a supervisor asks why a ticket disappeared.
 * `NoGlobalScopesTest` fails the build if one appears.
 */
final class Branch extends Model
{
    protected $table = 'branches';

    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
