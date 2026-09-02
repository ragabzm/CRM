<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;

/**
 * The one way an event gets written.
 *
 * Shared by every command so the event and the write cannot come apart: a
 * command that wrote the ticket and forgot the event would leave a history with
 * a hole in it, and the hole would be exactly the change someone is trying to
 * explain.
 */
trait AppendsEvents
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function appendEvent(
        Ticket $ticket,
        Actor $actor,
        string $type,
        array $payload = [],
    ): TicketEvent {
        $event = new TicketEvent;

        $event->forceFill([
            'ticket_id' => $ticket->getKey(),
            'event_type' => $type,
            'payload' => $payload,
            'actor_type' => $actor->kind(),
            'actor_id' => $actor->id(),
            'actor_reason' => $actor->reason(),
            // The version AFTER this change, so replaying the events
            // reproduces the version history exactly.
            'version_after' => $ticket->version,
            'created_at' => now(),
        ])->save();

        return $event;
    }
}
