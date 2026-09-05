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
     * The provider says it reached the person's device.
     *
     * Set ONLY by a delivery receipt from a provider that sends them, and
     * never inferred from a successful send — "we handed it over" and "it
     * arrived" are different facts, and an agent reading "delivered" is
     * entitled to believe the second one. Email providers do not report this;
     * WhatsApp and SMS gateways do, which is why the state arrives with them.
     */
    case Delivered = 'delivered';

    /**
     * The person opened it. WhatsApp reports this; nothing else does.
     *
     * Never set for a channel whose provider does not report it — an SMS
     * marked read would be the application claiming to know something it
     * cannot.
     */
    case Read = 'read';

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
