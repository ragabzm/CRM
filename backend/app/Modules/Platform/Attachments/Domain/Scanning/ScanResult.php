<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Domain\Scanning;

/**
 * What a scanner concluded about one file.
 *
 * Two outcomes only: clean or failed. "Unable to decide" is not an outcome —
 * it is a transport problem, and it is raised as ScannerUnreachable so the
 * caller cannot mistake "we could not check" for "we checked and it is fine".
 * That distinction is the entire safety property.
 */
final class ScanResult
{
    private function __construct(
        public readonly bool $clean,
        public readonly ?string $reason = null,
        /** @var array<string, mixed>|null Raw scanner output, for the record. */
        public readonly ?array $raw = null,
    ) {}

    public static function clean(?array $raw = null): self
    {
        return new self(true, null, $raw);
    }

    public static function failed(string $reason, ?array $raw = null): self
    {
        return new self(false, $reason, $raw);
    }
}
