<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use App\Modules\Tickets\Contracts\InboundProvenance;

/**
 * The answer when the Channels module is not present.
 *
 * Empty, not a guess. A conversation then renders without provenance chips,
 * which is what it did before there were any — as opposed to labelling every
 * message "agent" and quietly asserting something nobody checked.
 */
final class NoInboundProvenance implements InboundProvenance
{
    public function forMessages(array $messageIds): array
    {
        return [];
    }
}
