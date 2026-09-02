<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Domain;

/**
 * Removes credential-shaped values before they are written.
 *
 * At WRITE time, never at read time. Redacting on the way out would mean the
 * secret is really in the database and the protection is a formatting choice —
 * one raw query, one export, one future endpoint away from being undone. What
 * is never stored cannot leak.
 *
 * The patterns live in `config/audit.php` so a newly-invented credential-shaped
 * key is a config line rather than a deploy of this class.
 *
 * It matches on the KEY, not the value. Value-shaped detection (entropy, JWT
 * structure) both misses hand-written passwords and mangles legitimate data,
 * and the key is the part a developer controls and can be told about.
 */
final class AuditRedactor
{
    /**
     * @param  list<string>  $keyPatterns
     */
    public function __construct(
        private readonly array $keyPatterns,
        private readonly string $placeholder,
    ) {}

    public static function fromConfig(): self
    {
        /** @var list<string> $patterns */
        $patterns = config('audit.redaction.key_patterns', []);

        return new self($patterns, (string) config('audit.redaction.placeholder', '[REDACTED]'));
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     * @return array<array-key, mixed>|null
     */
    public function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return $this->walk($payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function walk(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                /*
                 * Replaced whole, including when the value is an array. A
                 * `credentials: {...}` object redacted key-by-key would still
                 * publish its structure, and the structure of a credential is
                 * itself a hint worth withholding.
                 */
                $result[$key] = $this->placeholder;

                continue;
            }

            $result[$key] = is_array($value) ? $this->walk($value) : $value;
        }

        return $result;
    }

    private function isSensitive(string $key): bool
    {
        foreach ($this->keyPatterns as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
