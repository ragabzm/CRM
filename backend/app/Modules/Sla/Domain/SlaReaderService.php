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

    /**
     * @param  list<string>  $ticketIds
     * @return array{at_risk: int|null, breached: int|null}
     */
    public function countsAmong(array $ticketIds): array
    {
        $counts = ['at_risk' => 0, 'breached' => 0];

        foreach ($this->forTickets($ticketIds) as $block) {
            /*
             * The SAME reading the row badge shows. Counting from
             * `sla_events.breached_at` instead would be cheaper and would
             * eventually disagree: that column is written by the sweep, which
             * runs on a schedule, so a ticket can be breached on the row and
             * not yet breached in the tally.
             */
            if ($block['state'] === SlaState::Breached->value) {
                $counts['breached']++;
            } elseif ($block['state'] === SlaState::AtRisk->value) {
                $counts['at_risk']++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<string>  $ticketIds
     * @return list<string>
     */
    public function idsInState(string $state, array $ticketIds): ?array
    {
        $matching = [];

        foreach ($this->forTickets($ticketIds) as $id => $block) {
            if ($block['state'] === $state) {
                $matching[] = (string) $id;
            }
        }

        return $matching;
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
