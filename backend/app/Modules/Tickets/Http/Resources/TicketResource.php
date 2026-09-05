<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Resources;

use App\Modules\Tickets\Contracts\ChannelAvailability;
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
     * @param  array<string, mixed>|null  $sla  Where this ticket stands against
     *        its targets, from the Sla module. Passed IN rather than fetched
     *        here: a list renders twenty-five of these, and a lookup per row
     *        would be the N+1 the batched reader exists to prevent.
     * @return array<string, mixed>
     */
    public static function toArray(Ticket $ticket, ?array $sla = null): array
    {
        return [
            'id' => (string) $ticket->getKey(),
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'customer_id' => (string) $ticket->customer_id,
            'channel' => $ticket->channel->value,

            /*
             * Whether the channel this arrived on still takes outbound.
             *
             * Sent with the ticket, not fetched when the composer opens,
             * because the composer has to know BEFORE the agent writes. Telling
             * somebody their reply cannot be sent after they have written it is
             * the failure this field exists to prevent.
             *
             * True when the ticket came in on a channel nobody has configured
             * an account for — there is nothing switched off, so nothing is
             * blocked.
             */
            'channel_account_active' => app(ChannelAvailability::class)->isOpen($ticket->channel->value),

            // The five properties the version guard protects. Returned together
            // so a stale-version refusal and a success carry the same shape.
            'status' => $ticket->status->value,

            /*
             * A PROPERTY beside the status, never inside it.
             *
             * `status` stays exactly Open · Pending · Resolved · Closed. An
             * escalated ticket keeps whichever of those it had, and every
             * filter, count and transition rule goes on seeing four values.
             *
             * This resource serves STAFF only. The portal builds its own shape
             * in `Tickets\Application\Portal\CustomerRequests`, and none of
             * these three fields appear there — escalation is a conversation
             * between colleagues about a customer, and showing a customer that
             * their own ticket has been escalated invites the question it was
             * meant to answer before they asked it.
             */
            /*
             * What the customer said, READ-ONLY for staff.
             *
             * There is no endpoint that lets an agent, a supervisor or an
             * administrator write these — not a PATCH field, not an admin
             * action, not a console setting. `UpdateTicketAttributes` cannot
             * reach them because they are not contended properties, and
             * `SatisfactionIsCustomersOnlyTest` fails if a write path
             * appears. A satisfaction figure staff can edit is a figure
             * nobody has any reason to believe.
             */
            'satisfaction' => $ticket->satisfaction,
            'satisfaction_comment' => $ticket->satisfaction_comment,
            'satisfaction_at' => $ticket->satisfaction_at?->toIso8601String(),

            'escalated_at' => $ticket->escalated_at?->toIso8601String(),
            'escalated_by' => $ticket->escalated_by,
            'escalation_reason' => $ticket->escalation_reason,
            'priority' => $ticket->priority->value,
            'category_id' => $ticket->category_id,
            'assignee_id' => $ticket->assignee_id,
            'department_id' => $ticket->department_id,

            'creator_type' => $ticket->creator_type,
            'creator_id' => $ticket->creator_id,
            'version' => $ticket->version,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),

            /*
             * Null means "not tracked", not "fine". A deployment with the SLA
             * engine off knows nothing about its targets, and an on_track it
             * cannot justify would be worse than an honest blank — the same
             * reason the counts strip shows a dash rather than a zero.
             */
            'sla' => $sla,
        ];
    }
}
