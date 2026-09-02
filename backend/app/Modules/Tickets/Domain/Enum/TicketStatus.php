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
    case Reopened = 'reopened';

    /**
     * Which statuses may follow this one.
     *
     * `closed` leads nowhere. It is the one terminal state: a closed ticket
     * that could be edited would mean an agreed outcome could be quietly
     * changed months later, and the answer to "we need to look at this again"
     * is a new ticket that references the old one.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Open => [self::Pending, self::Resolved, self::Closed],
            self::Pending => [self::Open, self::Resolved, self::Closed],
            self::Resolved => [self::Reopened, self::Closed],
            self::Reopened => [self::Open, self::Pending, self::Resolved, self::Closed],
            self::Closed => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
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
            'tickets.lifecycle_violation',
            'That status change is not allowed',
            422,
            $this === self::Closed
                ? 'This ticket is closed. Closed tickets cannot be changed — open a new one that references it.'
                : sprintf('A %s ticket cannot become %s.', $this->value, $next->value),
            ['from' => $this->value, 'to' => $next->value, 'allowed' => array_map(
                static fn (self $case): string => $case->value,
                $this->allowedNext(),
            )],
        );
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
