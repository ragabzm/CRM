<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain;

/**
 * Who sweeps which rows.
 *
 * One table, one shape, one reader — but not necessarily one retention period.
 * Mail volume and ERP volume are different by an order of magnitude, and the
 * compliance answer for "who we emailed" is not the compliance answer for
 * "what we synced", so a single number for both would be the wrong number
 * twice.
 *
 * A module that wants to keep its own period CLAIMS its rows here, and the
 * general sweep leaves them alone. The claim runs that way round on purpose:
 * this module must not know that mail exists. A list of exceptions maintained
 * here would be a list somebody has to remember to update, and the failure
 * would be silent — one integration's retention quietly deleting another's
 * history, which is the whole risk a shared table introduces.
 */
final class ExchangeRetention
{
    /** @var list<string> */
    private array $claimed = [];

    /**
     * "These rows are mine; do not sweep them."
     *
     * The claimant takes on the obligation in exchange: rows nobody prunes
     * grow for ever, so a claim without a sweep is worse than no claim.
     */
    public function claim(string $integration): void
    {
        if (! in_array($integration, $this->claimed, true)) {
            $this->claimed[] = $integration;
        }
    }

    /**
     * @return list<string>
     */
    public function claimed(): array
    {
        return $this->claimed;
    }
}
