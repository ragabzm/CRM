<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Assignment\AutoAssignment;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Events\TicketOpened;
use App\Modules\Tickets\Domain\Priority;
use App\Modules\Tickets\Domain\Reference\TicketReferenceAllocator;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\QueryException;

/**
 * Creates a ticket and its first event, together or not at all.
 */
final class CreateTicket
{
    /** Retries for a reference collision, which only the SQLite path can hit. */
    private const REFERENCE_ATTEMPTS = 3;

    /** What history calls the mapping table when it places a ticket. */
    public const AUTO_ASSIGN_REASON = 'auto_assign';

    public function __construct(
        private readonly TicketReferenceAllocator $references,
        private readonly ConnectionInterface $db,
        private readonly TicketEventRecorder $history,
        private readonly AutoAssignment $autoAssignment,
    ) {}

    public function handle(Actor $actor, CreateTicketInput $input): Ticket
    {
        /*
         * One transaction around the ticket AND its event. A ticket with no
         * `ticket.created` event is a ticket whose history starts mid-story,
         * and there is no later moment at which that could be repaired.
         */
        $ticket = $this->db->transaction(function () use ($actor, $input): Ticket {
            $ticket = $this->insert($actor, $input);

            /*
             * Inside the transaction, before the created event.
             *
             * A ticket must never be briefly visible as unassigned and then
             * assigned a moment later: an agent watching the unassigned queue
             * would see it appear and vanish, and the `ticket.created` event
             * would record a state the ticket was never really in.
             */
            $mapping = $this->applyMapping($ticket, $input);

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

            if ($mapping !== null) {
                /*
                 * Its own event, after the creation one, attributed to the
                 * system and NAMING the mapping row that matched.
                 *
                 * "Why is this on Dana's queue?" has to be answerable from the
                 * ticket. Folding the assignment into the created event would
                 * make an automatic assignment indistinguishable from a ticket
                 * somebody raised already assigned.
                 */
                $this->history->record(
                    (string) $ticket->getKey(),
                    TicketEventKind::AssigneeChanged,
                    Actor::system(self::AUTO_ASSIGN_REASON),
                    before: ['assignee_id' => null, 'department_id' => $input->departmentId],
                    after: [
                        'assignee_id' => $ticket->assignee_id,
                        'department_id' => $ticket->department_id,
                    ],
                    meta: ["mapping_id" => $mapping["mapping_id"]],
                    versionAfter: $ticket->version,
                );
            }

            return $ticket;
        });

        /*
         * After the transaction, deliberately.
         *
         * A listener that sends an acknowledgement must not run inside the
         * write: it would be sending mail about a ticket that could still be
         * rolled back, and the customer would have an email for something that
         * never happened.
         */
        Event::dispatch(new TicketOpened((string) $ticket->getKey(), $input->suppressAcknowledgement));

        return $ticket;
    }

    /**
     * Applies the mapping table, if it has anything to say.
     *
     * An assignee the ACTING AGENT supplied is never overridden — they were
     * looking at the ticket and made a decision, and a lookup table is not
     * better informed than that.
     *
     * @return array{assignee_id?: int, department_id?: int, mapping_id: int}|null
     */
    private function applyMapping(Ticket $ticket, CreateTicketInput $input): ?array
    {
        if ($ticket->assignee_id !== null) {
            return null;
        }

        $decision = $this->autoAssignment->decide($ticket->category_id, $ticket->department_id);

        if ($decision === null) {
            // No match, or the only match points at somebody who cannot take
            // work. Unassigned is a valid, visible, workable state.
            return null;
        }

        $ticket->forceFill(array_filter(
            [
                'assignee_id' => $decision['assignee_id'] ?? null,
                /*
                 * A department mapping MOVES the ticket and leaves it
                 * unassigned there. AD-22 already resolved a department; this
                 * is a recorded move on top of that answer, not a fifth rung
                 * on its ladder.
                 */
                'department_id' => $decision['department_id'] ?? null,
            ],
            static fn (?int $value): bool => $value !== null,
        ))->save();

        return $decision;
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
                /*
                 * Inherited from the customer, and NOT a parameter.
                 *
                 * A branch says where work happened, and the answer is
                 * wherever the customer's office is — not whatever a caller
                 * passed. Letting it be supplied would make the same customer
                 * produce tickets in three branches depending on which door
                 * they came through.
                 *
                 * Null when the customer has none, and that null has no
                 * consequence anywhere: nothing is refused, nothing is
                 * narrowed, nothing waits for it. Which is exactly why branch
                 * needs no resolution order of its own, unlike department.
                 */
                'branch_id' => DB::table('customers')->where('id', $input->customerId)->value('branch_id'),
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
