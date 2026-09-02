<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Actor;

/**
 * The application acting on its own behalf.
 *
 * Carries a REASON rather than an id, and the reason is required. "The system
 * closed it" is not an explanation anybody can act on; "scheduler:auto_close"
 * tells whoever is reading the history which job to go and look at.
 */
final class SystemActor extends Actor
{
    public function __construct(public readonly string $why) {}

    public function kind(): string
    {
        return 'system';
    }

    public function id(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'System';
    }

    public function reason(): ?string
    {
        return $this->why;
    }
}
