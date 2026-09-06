<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure;

use App\Modules\Integrations\Contracts\ErpResponse;
use App\Modules\Integrations\Contracts\ErpTransport;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The one generic REST adapter. There is no second, and no named one.
 *
 * What an ERP needs from us is a URL, a header and a shape — and every one of
 * those is configuration. A vendor adapter would be code somebody has to
 * maintain against release notes they never see, and the first one to break
 * would break silently in a nightly job.
 *
 * IT NEVER THROWS for a remote failure. An unreachable ERP is the ordinary
 * outcome of talking to somebody else's system, not an error in this product,
 * and a caller that had to catch it would be a caller that sometimes forgot.
 * The timing is measured either way, because a number that climbs is the first
 * sign of trouble.
 */
final class RestErpTransport implements ErpTransport
{
    public function send(
        string $method,
        string $url,
        array $headers,
        array $body,
        int $timeoutSeconds,
    ): ErpResponse {
        $started = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeoutSeconds)
                /*
                 * No retry HERE. Retrying inside the transport would hold the
                 * job for three timeouts and hide two of the attempts from the
                 * log — the retry belongs to the queued job, where it is
                 * bounded, backed off and visible as separate rows.
                 */
                ->send($method, $url, ['json' => $body]);

            return new ErpResponse(
                reached: true,
                status: $response->status(),
                body: $this->decode($response->body()),
                durationMs: $this->elapsed($started),
                error: $response->successful() ? null : $response->body(),
            );
        } catch (Throwable $e) {
            return ErpResponse::unreachable($e->getMessage(), $this->elapsed($started));
        }
    }

    public function name(): string
    {
        return 'rest';
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $body): array
    {
        $decoded = json_decode($body, true);

        /*
         * A body that is not JSON is kept as text rather than dropped. An ERP
         * returning an HTML error page is exactly the case somebody needs to
         * see, and "the response was not JSON" is not an answer.
         */
        return is_array($decoded) ? $decoded : ['raw' => mb_substr($body, 0, 2000)];
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
