<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Events;

/**
 * A ticket now belongs to somebody.
 *
 * Fired only when the assignee actually CHANGED. A command that reassigns a
 * ticket to the person who already has it has done nothing, and telling them
 * about it teaches them to ignore the notification.
 *
 * Carries the actor so the listener can decline to tell somebody about their
 * own doing.
 */
final class TicketAssigned
{
    public function __construct(
        public readonly string $ticketId,
        public readonly ?int $assigneeId,
        public readonly ?string $actorId,
        public readonly string $actorName,
    ) {}
}
