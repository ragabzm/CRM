<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Http\Middleware\IdempotencyKey;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly prune of expired idempotency records.
 *
 * The middleware also expires stale rows on read, so this is about keeping the
 * table small rather than about correctness.
 */
final class PruneIdempotencyKeysCommand extends Command
{
    protected $signature = 'platform:prune-idempotency-keys';

    protected $description = 'Delete idempotency_keys rows older than the retention window.';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subHours(IdempotencyKey::TTL_HOURS);

        $deleted = DB::table(IdempotencyKey::TABLE)
            ->where('created_at', '<', $cutoff)
            ->delete();

        // Console output is for whoever ran it by hand; the log line is what a
        // scheduled run leaves behind for anyone asking later why a key that
        // "should" have replayed did not.
        Log::info('Pruned expired idempotency keys.', [
            'deleted' => $deleted,
            'cutoff' => $cutoff->toIso8601String(),
        ]);

        $this->info("Pruned {$deleted} idempotency key(s) created before {$cutoff->toIso8601String()}.");

        return self::SUCCESS;
    }
}
