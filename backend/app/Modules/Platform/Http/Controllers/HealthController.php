<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The only endpoints that exist at the end of Story 1.1.
 *
 * They are here so the OpenAPI document is non-empty and so the idempotency and
 * problem-details machinery has something real to exercise end to end.
 */
final class HealthController
{
    /**
     * Liveness probe.
     *
     * Returns 200 as long as the web process can serve a request. It does not
     * touch the database on purpose: this answers "is this process up", not
     * "is the whole stack healthy".
     *
     * @response array{status: string}
     */
    public function show(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * Echoes the posted payload back.
     *
     * TEMPORARY (Story 1.1 only): the acceptance criteria require a write
     * endpoint to prove Idempotency-Key replay against, and no real one exists
     * yet. Remove this once Story 1.2+ introduces genuine writes.
     *
     * @response array{status: string, echo: array<string, mixed>}
     */
    public function echo(Request $request): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'echo' => $request->all(),
        ]);
    }
}
