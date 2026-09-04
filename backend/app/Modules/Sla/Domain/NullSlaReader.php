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

    /**
     * @param  list<string>  $ticketIds
     * @return array{at_risk: null, breached: null}
     */
    public function countsAmong(array $ticketIds): array
    {
        // Null, not zero. See the class note.
        return ['at_risk' => null, 'breached' => null];
    }

    /**
     * @param  list<string>  $ticketIds
     */
    public function idsInState(string $state, array $ticketIds): ?array
    {
        /*
         * Null and not `[]`: an empty array would mean "no ticket is at risk"
         * and filter the list down to nothing, which reads as an answer. The
         * caller must skip the filter entirely instead.
         */
        return null;
    }
}
