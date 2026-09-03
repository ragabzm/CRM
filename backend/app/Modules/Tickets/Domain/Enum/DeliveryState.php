<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Enum;

/**
 * Whether an outbound message reached anyone.
 *
 * Only outbound messages have one. An inbound message arrived by definition,
 * and an internal note is never sent — giving either a delivery state would be
 * claiming something about a journey they never made.
 */
enum DeliveryState: string
{
    /** Written and accepted, not yet handed to the mail pipeline. */
    case Queued = 'queued';

    /** The pipeline confirmed it left. Story 5.1 sets this. */
    case Sent = 'sent';

    /** It did not leave. The agent must be told, and offered a retry. */
    case Failed = 'failed';

    /**
     * Whether an agent can do anything about this state.
     *
     * Only a failure asks something of the person looking at it; the other two
     * are progress reports.
     */
    public function needsAttention(): bool
    {
        return $this === self::Failed;
    }
}
