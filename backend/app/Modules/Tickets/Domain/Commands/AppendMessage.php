<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use Illuminate\Database\ConnectionInterface;

/**
 * Adds a message to a ticket.
 *
 * TAKES NO VERSION, ON PURPOSE.
 *
 * The version guard exists for the five properties two people genuinely contend
 * over — where a second write silently reverts the first. Two people writing
 * different replies are not in that situation: they have both said something,
 * and both belong in the thread. Requiring a version here would make a reply
 * fail whenever a colleague happened to change the priority a moment earlier,
 * which on a busy ticket is most of the time, and would train people to retry
 * blindly until it went through.
 *
 * It also does not touch `version` or lock the ticket row. An append is not a
 * change to the ticket's contended state, and bumping the version would make
 * everyone else's open edit form stale for no reason.
 */
final class AppendMessage
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(
        Actor $actor,
        string $ticketId,
        MessageDirection $direction,
        string $body,
    ): TicketMessage {
        if (trim($body) === '') {
            throw ProblemException::make(
                'tickets.message_empty',
                'Write something before sending',
                422,
                'A message needs a body.',
            );
        }

        return $this->db->transaction(function () use ($actor, $ticketId, $direction, $body): TicketMessage {
            // Read, not locked: nothing here contends with anything.
            $ticket = Ticket::query()->whereKey($ticketId)->first();

            if ($ticket === null) {
                throw ProblemException::make(
                    'tickets.not_found',
                    'Ticket not found',
                    404,
                    "No ticket with id [{$ticketId}].",
                );
            }

            $message = new TicketMessage;

            $message->forceFill([
                'ticket_id' => $ticket->getKey(),
                // Denormalised from the ticket so the customer's whole
                // interaction history is one indexed read.
                'customer_id' => $ticket->customer_id,
                'direction' => $direction->value,
                'author_type' => $actor->kind(),
                'author_id' => $actor->id(),
                'author_name' => $actor->label(),
                'body' => $body,
                'sent_at' => now(),
            ])->save();

            return $message;
        });
    }
}
