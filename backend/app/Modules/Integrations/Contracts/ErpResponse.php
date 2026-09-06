<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Contracts;

/**
 * What came back, or the fact that nothing did.
 *
 * A value rather than an exception, because "the other end was down" is the
 * ordinary case for an integration and not an error in this product. The log
 * row is written from this either way, which is what makes a failure
 * diagnosable rather than a mystery.
 */
final readonly class ErpResponse
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public bool $reached,
        public ?int $status,
        public array $body,
        public int $durationMs,
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->reached && $this->status !== null && $this->status >= 200 && $this->status < 300;
    }

    public static function unreachable(string $why, int $durationMs): self
    {
        return new self(false, null, [], $durationMs, $why);
    }
}
