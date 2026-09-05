<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain;

use App\Modules\Tickets\Contracts\ChannelAvailability;
use Illuminate\Support\Facades\DB;

/**
 * Reads the switch an administrator threw.
 *
 * Memoised per INSTANCE, and bound as `scoped` so the container hands out a
 * fresh one per request. A static cache would answer the next request with the
 * previous one's state — and under a persistent worker it would answer every
 * request for the lifetime of the process, so a channel enabled at nine would
 * still read as disabled at five.
 *
 * @see ChannelsServiceProvider for the binding.
 */
final class ChannelAccountAvailability implements ChannelAvailability
{
    /** @var array<string, bool> */
    private array $answered = [];

    public function isOpen(string $channel): bool
    {
        if (! array_key_exists($channel, $this->answered)) {
            $accounts = DB::table('channel_accounts')->where('channel', $channel)->get(['is_active']);

            // No account configured means nothing was switched off.
            $this->answered[$channel] = $accounts->isEmpty()
                || $accounts->contains(static fn (object $row): bool => (bool) $row->is_active);
        }

        return $this->answered[$channel];
    }
}
