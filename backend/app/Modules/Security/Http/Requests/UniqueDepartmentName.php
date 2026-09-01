<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Department names are unique case-INSENSITIVELY.
 *
 * The database unique index is case-sensitive on Postgres, so "Billing" and
 * "billing" would both be accepted and then be indistinguishable to a reader
 * choosing between them in a dropdown. Comparing lowercased closes that.
 */
final class UniqueDepartmentName implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $query = DB::table('departments')->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))]);

        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail(__('validation.unique', ['attribute' => $attribute]));
        }
    }
}
