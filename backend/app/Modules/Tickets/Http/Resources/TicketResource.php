<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Resources;

use App\Modules\Tickets\Domain\Ticket;

/**
 * The one serialisation shape every ticket endpoint returns.
 *
 * `version` and the five contended fields are always present, because the
 * client needs them to make its next write — a response that omitted the
 * version would force a second fetch before anything could be edited.
 */
final class TicketResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Ticket $ticket): array
    {
        return [
            'id' => (string) $ticket->getKey(),
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'customer_id' => (string) $ticket->customer_id,
            'channel' => $ticket->channel->value,

            // The five properties the version guard protects. Returned together
            // so a stale-version refusal and a success carry the same shape.
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'category_id' => $ticket->category_id,
            'assignee_id' => $ticket->assignee_id,
            'department_id' => $ticket->department_id,

            'creator_type' => $ticket->creator_type,
            'creator_id' => $ticket->creator_id,
            'version' => $ticket->version,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }
}
