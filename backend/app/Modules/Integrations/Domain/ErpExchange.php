<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain;

use App\Modules\Integrations\Contracts\ErpResponse;
use App\Modules\Integrations\Contracts\ErpTransport;

/**
 * One call to the ERP, logged whatever happens.
 *
 * THE TEST ACTION RUNS THIS. Not a ping, not a reachability check — the same
 * path a real exchange takes, with the same headers, the same timeout and the
 * same log row. A test that only proved the host answered would pass on a
 * misconfigured credential and fail at 3am on the first real sync, which is
 * the exact thing an administrator pressed the button to avoid.
 */
final class ErpExchange
{
    public const INTEGRATION = 'erp';

    /**
     * The longest an ADMINISTRATOR'S test may hold the request.
     *
     * Sync exchanges run on the queue and may take the configured timeout —
     * up to two minutes — because nothing is waiting on them. The test action
     * is the one path a person waits on, and a request held for two minutes is
     * a browser that looks broken and a worker nobody else can use.
     *
     * Ten seconds is also long enough to be a real answer: an ERP that has not
     * responded in ten is not going to make the nightly sync window either.
     */
    public const INTERACTIVE_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly ErpTransport $transport,
        private readonly ErpSettings $settings,
        private readonly ExchangeLog $log,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public function call(
        string $method,
        string $path,
        array $body = [],
        int $attempt = 1,
        bool $interactive = false,
    ): ErpResponse {
        $url = rtrim($this->settings->endpoint(), '/').'/'.ltrim($path, '/');

        $headers = [
            'Accept' => 'application/json',
            $this->settings->authHeader() => $this->settings->credential(),
        ];

        /*
         * Logged BEFORE the call. An exchange that hangs or crashes the worker
         * still leaves evidence that it was tried, and the row already has the
         * secrets taken out of it.
         */
        $id = $this->log->queued(self::INTEGRATION, $url, $headers, $body, $attempt);

        $response = $this->transport->send(
            $method,
            $url,
            $headers,
            $body,
            $interactive
                ? min($this->settings->timeoutSeconds(), self::INTERACTIVE_TIMEOUT_SECONDS)
                : $this->settings->timeoutSeconds(),
        );

        if ($response->succeeded()) {
            $this->log->succeeded($id, $response->status, $response->body, $response->durationMs);
        } else {
            $this->log->failed(
                $id,
                $response->status,
                $response->error ?? 'The exchange did not succeed.',
                $response->durationMs,
            );
        }

        return $response;
    }
}
