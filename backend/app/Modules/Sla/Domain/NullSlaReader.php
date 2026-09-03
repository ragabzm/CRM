<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain;

use App\Modules\Tickets\Contracts\SlaReader;

/**
 * The answer when nothing is tracking service levels.
 *
 * Returns nothing at all rather than a cheerful `on_track`. A deployment with
 * the engine switched off knows nothing about its targets, and saying "on
 * track" would be a claim it is in no position to make — the same reason the
 * counts strip shows a dash rather than a zero.
 */
final class NullSlaReader implements SlaReader
{
    /**
     * @param  list<string>  $ticketIds
     * @return array<string, array<string, mixed>>
     */
    public function forTickets(array $ticketIds): array
    {
        return [];
    }
}
