<?php

declare(strict_types=1);

namespace App\Modules\Security\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The one place a password is judged.
 *
 * Every flow that sets a password — reset, change, and any future
 * administrator-creates-user flow — validates through this rule, so the policy
 * is a configuration value rather than a number repeated across form requests
 * that will eventually disagree.
 *
 * Reports EVERY unmet requirement at once rather than the first: a reader who
 * has to resubmit four times to discover four rules will pick a worse password
 * than one who is told the rules up front.
 */
final class PasswordPolicy implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('auth.password_policy.invalid')->translate();

            return;
        }

        /** @var array{min_length:int,require_upper:bool,require_lower:bool,require_digit:bool,require_symbol:bool} $policy */
        $policy = config('auth.password_policy');

        $failures = [];

        if (mb_strlen($value) < $policy['min_length']) {
            $failures[] = __('auth.password_policy.min_length', ['count' => $policy['min_length']]);
        }

        if ($policy['require_upper'] && preg_match('/\p{Lu}/u', $value) !== 1) {
            $failures[] = __('auth.password_policy.upper');
        }

        if ($policy['require_lower'] && preg_match('/\p{Ll}/u', $value) !== 1) {
            $failures[] = __('auth.password_policy.lower');
        }

        if ($policy['require_digit'] && preg_match('/\d/', $value) !== 1) {
            $failures[] = __('auth.password_policy.digit');
        }

        // Anything that is not a letter, a digit or whitespace. Defining symbols
        // by exclusion accepts the whole Unicode punctuation range rather than a
        // hard-coded ASCII list nobody can remember.
        if ($policy['require_symbol'] && preg_match('/[^\p{L}\p{N}\s]/u', $value) !== 1) {
            $failures[] = __('auth.password_policy.symbol');
        }

        foreach ($failures as $failure) {
            $fail($failure);
        }
    }
}
