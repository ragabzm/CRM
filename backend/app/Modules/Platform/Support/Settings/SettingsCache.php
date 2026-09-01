<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Caches the resolved settings map.
 *
 * Settings are read on nearly every request and written a few times a year, so
 * the cache is what keeps "read a setting" from being a query. It is busted
 * synchronously by the same request that writes, which is what makes a change
 * take effect immediately rather than after a TTL.
 */
final class SettingsCache
{
    public const KEY = 'platform:settings:resolved';

    public const TTL_SECONDS = 3600;

    /**
     * @param  callable(): array<string, mixed>  $compute
     * @return array<string, mixed>
     */
    public function resolved(callable $compute): array
    {
        try {
            /** @var array<string, mixed> $values */
            $values = Cache::remember(self::KEY, self::TTL_SECONDS, $compute);

            return $values;
        } catch (Throwable $e) {
            /*
             * A cache outage must not take the product down. Settings are
             * readable from the database directly — slower, but correct — so a
             * failure here degrades performance rather than availability.
             */
            Log::warning('Settings cache unavailable; reading through to the database.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $compute();
        }
    }

    public function forget(): void
    {
        try {
            Cache::forget(self::KEY);
        } catch (Throwable $e) {
            // If the cache cannot be cleared, the next read falls through to the
            // database anyway; losing the ability to forget is not a reason to
            // fail the write that just succeeded.
            Log::warning('Could not clear the settings cache.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
