<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Enum;

use App\Modules\Platform\Exceptions\ProblemException;

/**
 * Where a ticket is in its life.
 *
 * The lifecycle lives HERE rather than in the command, because "may a closed
 * ticket be reopened?" is a question about the domain, not about an HTTP
 * handler — and a second copy of the table in a controller is the copy that
 * would drift.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /*
     * Four states, and deliberately no `reopened`.
     *
     * A reopened ticket IS open — it needs someone to work it, it belongs in
     * the open queue, and every filter that means "needs attention" would have
     * to remember to include a fifth value or silently miss it. That the ticket
     * came back is a fact about its HISTORY, which the event stream records
     * exactly, not a fifth thing it can be.
     *
     * Also absent: `new` and `cancelled`. A ticket is born open, and one that
     * should not have existed is closed with a reason like any other.
     */

    /**
     * Which statuses may follow this one.
     *
     * Delegates to TicketTransitions, which is the single source of truth the
     * story requires — this method is the convenient reading of that table, not
     * a second copy of it.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return TicketTransitions::from($this);
    }

    public function canMoveTo(self $next): bool
    {
        return TicketTransitions::allowed($this, $next);
    }

    /**
     * @throws ProblemException when the move is not allowed.
     */
    public function assertCanMoveTo(self $next): void
    {
        if ($this === $next) {
            // Setting a status to what it already is changes nothing; refusing
            // it would make a retried request fail.
            return;
        }

        if ($this->canMoveTo($next)) {
            return;
        }

        throw ProblemException::make(
            'tickets.transition_forbidden',
            'That status change is not allowed',
            409,
            sprintf('A %s ticket cannot become %s.', $this->value, $next->value),
            ['from' => $this->value, 'to' => $next->value, 'allowed' => array_map(
                static fn (self $case): string => $case->value,
                $this->allowedNext(),
            )],
        );
    }

    /** @return list<string> */
    /**
     * The states that still need someone's attention.
     *
     * "Open" in the sense a customer means it: their request has not been dealt
     * with yet. Resolved and closed are finished — counting them in "open
     * tickets" would tell an agent this person has eleven live problems when
     * they have one.
     *
     * Here rather than as a literal list at each call site, because the list
     * that gets forgotten when a state is added is the one that quietly starts
     * lying.
     *
     * @return list<self>
     */
    public static function openStates(): array
    {
        return [self::Open, self::Pending];
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
