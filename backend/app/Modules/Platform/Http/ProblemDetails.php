<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http;

use InvalidArgumentException;

/**
 * An RFC 9457 problem document.
 *
 * The `code` is the stable machine identifier and is shaped `module.condition`
 * (see self::CODE_PATTERN). The `type` URI is derived from it rather than being
 * passed in separately, so the two can never drift apart.
 */
final class ProblemDetails
{
    /**
     * Machine codes are `module.condition` in lower snake case. Enforced in the
     * constructor so a malformed code fails at the point it is written, not in
     * a consumer's error-handling branch months later.
     */
    public const CODE_PATTERN = '/^[a-z0-9_]+\.[a-z0-9_]+$/';

    public const CONTENT_TYPE = 'application/problem+json';

    /**
     * The codes the Platform module owns. Every framework-level failure maps to
     * one of these; feature modules add their own (`customers.not_found`, ...)
     * as they gain real endpoints.
     */
    public const PLATFORM_CODES = [
        'platform.internal_error',
        'platform.validation_failed',
        'platform.unauthorized',
        'platform.forbidden',
        'platform.not_found',
        'platform.method_not_allowed',
        'platform.conflict',
        'platform.too_many_requests',
        'platform.request_failed',
        'platform.idempotency_conflict',
        'platform.idempotency_in_flight',
    ];

    /**
     * Codes owned by the Security module. Listed here so the shape of the
     * vocabulary stays visible in one place; the module raises them itself.
     */
    public const SECURITY_CODES = [
        'security.invalid_credentials',
        'security.session_expired',
        'security.reset_token_invalid',
    ];

    /**
     * @param  array<string, mixed>  $extensions  Extra RFC 9457 members (e.g. `errors` for a 422).
     */
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        public readonly int $status,
        public readonly ?string $detail = null,
        public readonly array $extensions = [],
    ) {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException(
                "Problem code [{$code}] must be shaped module.condition matching ".self::CODE_PATTERN.'.'
            );
        }

        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException("Problem status [{$status}] must be a 4xx or 5xx code.");
        }
    }

    public function typeUri(string $baseUri): string
    {
        return rtrim($baseUri, '/').'/'.$this->code;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $baseUri, string $instance, string $traceId): array
    {
        // Order matches RFC 9457's own member ordering, then our two extensions.
        return [
            'type' => $this->typeUri($baseUri),
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'instance' => $instance,
            'code' => $this->code,
            'trace_id' => $traceId,
            ...$this->extensions,
        ];
    }
}
