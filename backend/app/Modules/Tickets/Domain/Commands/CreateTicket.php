<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Priority;
use App\Modules\Tickets\Domain\Reference\TicketReferenceAllocator;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

/**
 * Creates a ticket and its first event, together or not at all.
 */
final class CreateTicket
{
    /** Retries for a reference collision, which only the SQLite path can hit. */
    private const REFERENCE_ATTEMPTS = 3;

    public function __construct(
        private readonly TicketReferenceAllocator $references,
        private readonly ConnectionInterface $db,
        private readonly TicketEventRecorder $history,
    ) {}

    public function handle(Actor $actor, CreateTicketInput $input): Ticket
    {
        /*
         * One transaction around the ticket AND its event. A ticket with no
         * `ticket.created` event is a ticket whose history starts mid-story,
         * and there is no later moment at which that could be repaired.
         */
        return $this->db->transaction(function () use ($actor, $input): Ticket {
            $ticket = $this->insert($actor, $input);

            /*
             * `before` is null: nothing preceded a ticket's creation. `after`
             * carries the state it was born with, which is what makes every
             * later diff readable against a known starting point.
             */
            $this->history->record(
                (string) $ticket->getKey(),
                TicketEventKind::Created,
                $actor,
                before: null,
                after: [
                    'subject' => $ticket->subject,
                    'channel' => $ticket->channel->value,
                    'status' => $ticket->status->value,
                    'priority' => $ticket->priority->value,
                    'category_id' => $ticket->category_id,
                    'department_id' => $ticket->department_id,
                    'assignee_id' => $ticket->assignee_id,
                ],
                versionAfter: $ticket->version,
            );

            return $ticket;
        });
    }

    private function insert(Actor $actor, CreateTicketInput $input): Ticket
    {
        for ($attempt = 1; $attempt <= self::REFERENCE_ATTEMPTS; $attempt++) {
            $ticket = new Ticket;

            $ticket->forceFill([
                'reference' => $this->references->nextReference(),
                'subject' => trim($input->subject),
                'description' => $input->description,
                'customer_id' => $input->customerId,
                'channel' => $input->channel->value,
                'category_id' => $input->categoryId,
                'priority' => ($input->priority ?? Priority::Normal)->value,
                'status' => TicketStatus::Open->value,
                'department_id' => $input->departmentId,
                'creator_type' => $actor->kind(),
                'creator_id' => $actor->id(),
                // Unassigned: it goes to the pool, where whoever picks up work
                // next can see it. Auto-assigning at creation would hide it
                // from everyone else.
                'assignee_id' => null,
                'version' => 1,
            ]);

            try {
                $ticket->save();

                return $ticket;
            } catch (QueryException $e) {
                // Only a duplicate reference is worth retrying; anything else
                // is a real failure and must surface.
                if ($attempt === self::REFERENCE_ATTEMPTS || ! str_contains($e->getMessage(), 'reference')) {
                    throw $e;
                }
            }
        }

        throw ProblemException::make(
            'tickets.reference_unavailable',
            'Could not allocate a ticket reference',
            503,
            'Please try again.',
        );
    }
}
