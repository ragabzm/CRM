<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Http\Middleware\IdempotencyKey;
use App\Modules\Platform\Http\ProblemDetails;

/**
 * Adds the cross-cutting parts of the contract to the generated document.
 *
 * Scramble infers each operation from its controller, but the problem+json error
 * shape and the Idempotency-Key requirement are enforced by middleware and so
 * are invisible to that inference. Injecting them here is what makes the
 * generated TypeScript client aware of how errors and retries actually behave —
 * without it the client would be typed for the happy path only.
 */
final class OpenApiContract
{
    private const WRITE_METHODS = ['post', 'put', 'patch', 'delete'];

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public static function decorate(array $spec): array
    {
        $spec = self::pinEnvironmentDerivedFields($spec);

        $spec['components']['schemas']['Problem'] = self::problemSchema();

        /** @var array<string, array<string, mixed>> $paths */
        $paths = $spec['paths'] ?? [];

        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $operation['responses'] = self::normaliseStatusKeys($operation['responses'] ?? []);

                // `default` is OpenAPI's own "any other status" slot, which is
                // exactly right here: every non-2xx response in this API is a
                // problem document, whatever its status.
                $operation['responses']['default'] = self::problemResponse('An RFC 9457 problem document.');

                if (in_array(strtolower((string) $method), self::WRITE_METHODS, true)) {
                    $operation = self::withIdempotency($operation);
                }

                $paths[$path][$method] = $operation;
            }
        }

        $spec['paths'] = $paths;

        return $spec;
    }

    /**
     * Scramble fills info.title from APP_NAME and the server URL from APP_URL.
     * That would make the committed contract depend on whose environment
     * generated it — CI, with no .env, produces "Laravel" and
     * "http://localhost" and the drift check fails on a document nobody
     * changed. A contract has to be a function of the routes alone, so both
     * fields are pinned here.
     *
     * The server URL is relative on purpose: one document describes the API
     * wherever it is deployed, and the client supplies its own base URL.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private static function pinEnvironmentDerivedFields(array $spec): array
    {
        $spec['info']['title'] = 'Ragab CRM API';

        $spec['servers'] = [[
            'url' => '/api/v1',
            'description' => 'Relative to the deployment host, e.g. http://localhost:8000 locally.',
        ]];

        return $spec;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private static function withIdempotency(array $operation): array
    {
        $parameters = $operation['parameters'] ?? [];

        $parameters[] = [
            'name' => IdempotencyKey::HEADER,
            'in' => 'header',
            'required' => true,
            'description' => sprintf(
                'A ULID or UUID that identifies this write attempt. Repeating a request with the same key replays the stored response instead of acting twice; reusing a key with a different body returns 409. Keys are retained for %d hours.',
                IdempotencyKey::TTL_HOURS
            ),
            'schema' => ['type' => 'string', 'maxLength' => 40],
        ];

        $operation['parameters'] = $parameters;

        $operation['responses']['409'] = self::problemResponse(
            'The Idempotency-Key was already used for a different request (code: platform.idempotency_conflict).'
        );
        $operation['responses']['425'] = self::problemResponse(
            'A concurrent request with the same Idempotency-Key is still in flight (code: platform.idempotency_in_flight).'
        );

        return $operation;
    }

    /**
     * Response codes are strings in OpenAPI. Symfony's YAML dumper writes
     * integer keys unquoted, which some tooling then reads back as numbers.
     *
     * @param  array<array-key, mixed>  $responses
     * @return array<string, mixed>
     */
    private static function normaliseStatusKeys(array $responses): array
    {
        $normalised = [];

        foreach ($responses as $status => $response) {
            $normalised[(string) $status] = $response;
        }

        return $normalised;
    }

    /**
     * @return array<string, mixed>
     */
    private static function problemResponse(string $description): array
    {
        return [
            'description' => $description,
            'content' => [
                ProblemDetails::CONTENT_TYPE => [
                    'schema' => ['$ref' => '#/components/schemas/Problem'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function problemSchema(): array
    {
        return [
            'type' => 'object',
            'title' => 'Problem',
            'description' => 'RFC 9457 problem details. Every 4xx and 5xx response in this API has this shape.',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'description' => 'A URI identifying the problem type. Always the base URI plus `code`.',
                ],
                'title' => ['type' => 'string', 'description' => 'A short, human-readable summary.'],
                'status' => ['type' => 'integer', 'minimum' => 400, 'maximum' => 599],
                'detail' => [
                    'type' => ['string', 'null'],
                    'description' => 'A human-readable explanation specific to this occurrence.',
                ],
                'instance' => ['type' => 'string', 'description' => 'The request URI this problem occurred on.'],
                'code' => [
                    'type' => 'string',
                    'pattern' => '^[a-z0-9_]+\.[a-z0-9_]+$',
                    'description' => 'Stable machine identifier shaped `module.condition`. Branch on this, never on `title`.',
                    'examples' => ProblemDetails::PLATFORM_CODES,
                ],
                'trace_id' => [
                    'type' => 'string',
                    'description' => 'Correlation id for this request; matches the X-Request-Id response header and the request_id in the logs.',
                ],
                'errors' => [
                    'type' => 'object',
                    'description' => 'Present on validation failures: field name to list of messages.',
                    'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            'required' => ['type', 'title', 'status', 'instance', 'code', 'trace_id'],
        ];
    }
}
