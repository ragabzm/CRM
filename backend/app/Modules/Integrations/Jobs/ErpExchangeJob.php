<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Integrations\Domain\ErpExchange;
use App\Modules\Integrations\Domain\ErpSettings;
use App\Modules\Integrations\Domain\ExchangeLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Every exchange, off the request.
 *
 * NOTHING HERE RUNS IN-REQUEST, and that is the whole reason an ERP being down
 * is invisible to the ticketing loop. An agent pressing Resolve does not wait
 * on somebody else's system, and an ERP that has been unreachable since
 * Tuesday does not slow down a single screen.
 *
 * BOUNDED RETRY WITH BACKOFF. Four attempts over about ten minutes, and then a
 * terminal failure recorded in the log where an administrator can see it.
 * Unbounded retries are worse than none: they turn one broken configuration
 * into a queue that never drains and a log nobody can read.
 */
final class ErpExchangeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Four attempts, and then it is somebody's job to look. */
    public int $tries = 4;

    /**
     * Half a minute, then one, two, five.
     *
     * Growing, because an ERP that just refused a call is most likely to
     * refuse the next one too — and four calls a second apart is not a retry,
     * it is the same failure four times.
     *
     * @var list<int>
     */
    public array $backoff = [30, 60, 120, 300];

    /**
     * A hard ceiling on the whole job, above the transport's own timeout.
     *
     * Without it a worker can be held by a socket that never closes — and one
     * held worker is one fewer for every other queue in the product.
     */
    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $body = [],
    ) {}

    public function handle(ErpExchange $exchange, ErpSettings $settings): void
    {
        if (! $settings->enabled()) {
            /*
             * Switched off between the dispatch and the run. Silently done,
             * not failed: an administrator who turned the integration off
             * meant for it to stop, and a queue of failures afterwards would
             * be a log of them obeying.
             */
            return;
        }

        $response = $exchange->call($this->method, $this->path, $this->body, $this->attempts());

        if (! $response->succeeded()) {
            /*
             * Thrown so the queue retries with the backoff above. The log row
             * is already written either way — including on the last attempt,
             * which is what makes a terminal failure something an
             * administrator can read rather than something they infer from
             * silence.
             */
            throw new \RuntimeException(
                $response->error ?? 'The ERP exchange did not succeed.',
            );
        }
    }

    /**
     * Retries exhausted — say so where an administrator will look.
     *
     * The four attempts are already in the log. What this adds is the fact
     * that there will not be a fifth, which is the difference between "it is
     * still trying" and "this needs a person", and is not inferable from a
     * row that looks exactly like the three before it.
     */
    public function failed(?\Throwable $e): void
    {
        app(ExchangeLog::class)->abandoned(
            ErpExchange::INTEGRATION,
            $this->path,
            $e?->getMessage() ?? 'The exchange was abandoned after the last attempt.',
            $this->tries,
        );
    }
}
