<?php

declare(strict_types=1);

namespace App\Modules\Sla\Domain;

/**
 * Where a timer stands.
 *
 * Five, not three. The story asks for on_track / at_risk / breached, and those
 * are the three that describe a RUNNING clock — but a clock can also have
 * stopped for a good reason, and conflating either of those with "on track"
 * makes the indicator lie:
 *
 *   `met`    — answered or resolved inside the target. Finished, and finished
 *              well. Showing it as on_track would leave it in a list of things
 *              still to do.
 *   `paused` — the ticket is waiting on the customer, or the desk is shut. The
 *              clock is not running, and an agent looking at a paused ticket
 *              should not be pushed to act on it.
 */
enum SlaState: string
{
    case OnTrack = 'on_track';

    case AtRisk = 'at_risk';

    case Breached = 'breached';

    case Met = 'met';

    case Paused = 'paused';

    /** Whether this state should draw the eye on a list. */
    public function needsAttention(): bool
    {
        return $this === self::AtRisk || $this === self::Breached;
    }

    /** Whether the clock is still running against a target. */
    public function isLive(): bool
    {
        return $this === self::OnTrack || $this === self::AtRisk || $this === self::Breached;
    }
}
