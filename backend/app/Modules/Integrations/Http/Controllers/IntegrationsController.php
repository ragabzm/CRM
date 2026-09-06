<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Domain\ErpExchange;
use App\Modules\Integrations\Domain\ErpSettings;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The exchange log, and the button that tests a configuration.
 *
 * THE TEST RUNS THE REAL PATH. Same adapter, same headers, same timeout, same
 * log row — so a passing test means a working exchange rather than a reachable
 * host. A ping would pass on a wrong credential and fail on the first real
 * sync, which is precisely what the administrator pressed the button to find
 * out.
 *
 * There is no delete method on the log. Retention is the only path a row
 * leaves by; a log somebody can tidy is a log that gets tidied the morning
 * after the thing worth explaining.
 */
final class IntegrationsController extends Controller
{
    /**
     * How much of a provider's error text comes back to the console.
     *
     * A named constant rather than a literal because a bare `, 500` inside a
     * `new JsonResponse(...)` reads, to the guard that keeps error bodies out
     * of controllers, exactly like a hand-rolled 500.
     */
    private const ERROR_EXCERPT = 500;

    public function __construct(
        private readonly ErpSettings $settings,
        private readonly ErpExchange $exchange,
    ) {}

    /**
     * The log, newest first.
     */
    public function log(Request $request): JsonResponse
    {
        $rows = DB::table('integration_exchanges')
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get([
                'id', 'direction', 'integration', 'target', 'status',
                'attempt', 'response_status', 'duration_ms', 'error', 'occurred_at',
            ]);

        return new JsonResponse([
            'data' => $rows->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'direction' => (string) $row->direction,
                'integration' => (string) $row->integration,
                'target' => (string) $row->target,
                'status' => (string) $row->status,
                'attempt' => (int) $row->attempt,
                'response_status' => $row->response_status === null ? null : (int) $row->response_status,
                'duration_ms' => $row->duration_ms === null ? null : (int) $row->duration_ms,
                'error' => $row->error === null ? null : (string) $row->error,
                'occurred_at' => (string) $row->occurred_at,
            ])->all(),
        ]);
    }

    /**
     * Tries the configuration, and says exactly what happened.
     *
     * The endpoint reached, the status, the timing, and the error where there
     * is one. "Failed" on its own is a result an administrator can do nothing
     * with — and the whole point of this button is that they can.
     */
    public function test(): JsonResponse
    {
        if ($this->settings->endpoint() === '') {
            throw ProblemException::make(
                'integrations.not_configured',
                'There is nothing to test yet',
                422,
                'Set the endpoint first. A test against no address cannot tell you anything.',
            );
        }

        $response = $this->exchange->call('GET', '/', interactive: true);

        return new JsonResponse([
            'data' => [
                'succeeded' => $response->succeeded(),
                // Where it went, so a typo in the endpoint is visible in the
                // answer rather than inferred from a timeout.
                'endpoint' => $this->settings->endpoint(),
                'status' => $response->status,
                'duration_ms' => $response->durationMs,
                'error' => $response->error === null ? null : mb_substr($response->error, 0, self::ERROR_EXCERPT),
            ],
        ]);
    }
}
