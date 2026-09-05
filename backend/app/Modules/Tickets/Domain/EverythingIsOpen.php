<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use App\Modules\Tickets\Contracts\ChannelAvailability;

/**
 * The answer when nobody has configured any channels.
 *
 * Bound by Tickets itself so the module works on its own — in a unit test, in a
 * deployment that has not enabled the Channels module, or during the migration
 * that creates the table this would otherwise read. "Open" is the honest
 * answer: there is no account, so nothing has been switched off.
 */
final class EverythingIsOpen implements ChannelAvailability
{
    public function isOpen(string $channel): bool
    {
        return true;
    }
}
