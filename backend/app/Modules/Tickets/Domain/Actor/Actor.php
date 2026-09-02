<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Actor;

/**
 * Who is doing this.
 *
 * Passed explicitly into every command, never read from ambient state. Domain
 * code that called `auth()` would be unusable from a queue worker, a console
 * command or a test — and the auto-close job in a later story is exactly a
 * caller with no session at all.
 *
 * Making it a parameter also means the answer to "who changed this?" is
 * something the caller had to decide and can be reviewed, rather than whatever
 * happened to be in the container.
 */
abstract class Actor
{
    /** `staff` | `portal` | `system` — stored on the ticket and every event. */
    abstract public function kind(): string;

    /** Null for the system, which has no identity to record. */
    abstract public function id(): ?string;

    /** A human-readable name for the event trail. */
    abstract public function label(): string;

    /** Why the system acted. Null for a person, who needs no justification. */
    public function reason(): ?string
    {
        return null;
    }

    public static function staff(string $userId, string $displayName): StaffActor
    {
        return new StaffActor($userId, $displayName);
    }

    public static function portal(string $portalAccountId, string $displayName): PortalActor
    {
        return new PortalActor($portalAccountId, $displayName);
    }

    public static function system(string $reason): SystemActor
    {
        return new SystemActor($reason);
    }
}
