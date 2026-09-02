<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

use Illuminate\Contracts\Validation\Validator;

/**
 * A preferred channel has to be one the customer actually has.
 *
 * "Prefers email" on a record holding only a phone number is a promise the
 * product cannot keep: the first automated reply goes nowhere, silently, and
 * the customer is recorded as contacted.
 */
final class PreferredChannelRule
{
    public static function attach(Validator $validator, mixed $preferred, mixed $identifiers): void
    {
        $validator->after(function (Validator $validator) use ($preferred, $identifiers): void {
            if (! is_string($preferred) || $preferred === '' || ! is_array($identifiers)) {
                return;
            }

            $kinds = [];

            foreach ($identifiers as $identifier) {
                if (is_array($identifier) && isset($identifier['kind']) && is_string($identifier['kind'])) {
                    $kinds[] = $identifier['kind'];
                }
            }

            if (! in_array($preferred, $kinds, true)) {
                $validator->errors()->add(
                    'preferred_channel',
                    "Add a {$preferred} identifier, or choose a channel this customer has.",
                );
            }
        });
    }
}
