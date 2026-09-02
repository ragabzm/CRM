<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Actor;

/**
 * A customer acting through the portal.
 *
 * A separate type from StaffActor rather than a flag, because the two are
 * different populations with different guards — and a boolean is the kind of
 * thing that gets defaulted wrong.
 */
final class PortalActor extends Actor
{
    public function __construct(
        public readonly string $portalAccountId,
        public readonly string $displayName,
    ) {}

    public function kind(): string
    {
        return 'portal';
    }

    public function id(): ?string
    {
        return $this->portalAccountId;
    }

    public function label(): string
    {
        return $this->displayName;
    }
}
