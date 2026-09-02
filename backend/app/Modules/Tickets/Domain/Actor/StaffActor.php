<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Actor;

final class StaffActor extends Actor
{
    public function __construct(
        public readonly string $userId,
        public readonly string $displayName,
    ) {}

    public function kind(): string
    {
        return 'staff';
    }

    public function id(): ?string
    {
        return $this->userId;
    }

    public function label(): string
    {
        return $this->displayName;
    }
}
