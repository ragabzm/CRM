<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Settings;

use Closure;

/**
 * What an administrator is allowed to change, and within what bounds.
 *
 * A setting exists only if it is DECLARED here — there is no way to write an
 * arbitrary key. That is the difference between a settings table and a
 * key-value dumping ground: every row has a type, a default, and a rule
 * someone wrote down, so "what can be configured?" is answerable by reading
 * the registry rather than by querying production.
 */
final class SettingDefinition
{
    /**
     * @param  Closure(mixed): (true|string)  $validator  Returns true, or a message.
     * @param  list<string>|null  $allowedValues  Required for Enum.
     * @param  bool  $secret  Masked in every response; see redactedValue().
     */
    public function __construct(
        public readonly string $key,
        public readonly SettingType $type,
        public readonly mixed $default,
        public readonly ?Closure $validator = null,
        public readonly ?array $allowedValues = null,
        public readonly bool $secret = false,
        public readonly string $summary = '',
    ) {}

    /**
     * Validates a candidate value.
     *
     * Returns true, or the reason it was refused. The reason is shown to the
     * administrator, so it names the bound rather than saying "invalid".
     */
    public function validate(mixed $value): true|string
    {
        $typeError = $this->validateType($value);

        if ($typeError !== true) {
            return $typeError;
        }

        if ($this->type === SettingType::Enum) {
            $allowed = $this->allowedValues ?? [];

            if (! in_array($value, $allowed, true)) {
                return sprintf('Must be one of: %s.', implode(', ', $allowed));
            }
        }

        if ($this->validator !== null) {
            return ($this->validator)($value);
        }

        return true;
    }

    private function validateType(mixed $value): true|string
    {
        return match ($this->type) {
            SettingType::Bool => is_bool($value) ? true : 'Must be true or false.',
            SettingType::Int => is_int($value) ? true : 'Must be a whole number.',
            SettingType::DurationSeconds => is_int($value) && $value >= 0
                ? true
                : 'Must be a whole number of seconds, zero or more.',
            SettingType::String, SettingType::Enum => is_string($value) ? true : 'Must be text.',
            SettingType::Json => is_array($value) ? true : 'Must be a structured value.',
        };
    }

    /**
     * What a response may show.
     *
     * A secret is never echoed back, not even to the administrator who set it:
     * a value that can be read back is a value that leaks through a screen
     * share, a browser cache, or a support session. The console shows whether
     * one is set, not what it is.
     */
    public function redactedValue(mixed $value): mixed
    {
        if (! $this->secret) {
            return $value;
        }

        return $value === null || $value === '' ? null : '••••••••';
    }
}
