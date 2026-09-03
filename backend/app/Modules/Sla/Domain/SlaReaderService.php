<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain;

use App\Modules\Tickets\Contracts\SlaReader;

/**
 * The live answer, computed on read.
 *
 * Never stored. A stored state is wrong the second after it is written — the
 * clock keeps moving — and keeping it fresh would need a job that can fall
 * behind and start lying while every screen shows its output as fact.
 */
final class SlaReaderService implements SlaReader
{
    public function __construct(
        private readonly TicketTimelineLoader $loader,
        private readonly SlaClock $clock,
    ) {}

    /**
     * @param  list<string>  $ticketIds
     * @return array<string, array<string, mixed>>
     */
    public function forTickets(array $ticketIds): array
    {
        $blocks = [];

        foreach ($this->loader->forTickets($ticketIds) as $id => $timeline) {
            $readings = $this->clock->read($timeline);

            $blocks[$id] = [
                'response' => $readings[SlaClock::RESPONSE]->toArray(),
                'resolution' => $readings[SlaClock::RESOLUTION]->toArray(),
                /*
                 * The worse of the two, for a list that has room for one badge.
                 * A ticket that answered fast and is now three days late on
                 * resolution must not read as fine.
                 */
                'state' => $this->worst(
                    $readings[SlaClock::RESPONSE]->state,
                    $readings[SlaClock::RESOLUTION]->state,
                )->value,
            ];
        }

        return $blocks;
    }

    /** Severity order, worst first. */
    private function worst(SlaState $a, SlaState $b): SlaState
    {
        $order = [
            SlaState::Breached->value => 0,
            SlaState::AtRisk->value => 1,
            SlaState::Paused->value => 2,
            SlaState::OnTrack->value => 3,
            SlaState::Met->value => 4,
        ];

        return $order[$a->value] <= $order[$b->value] ? $a : $b;
    }
}
