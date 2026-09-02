<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Reference;

use Illuminate\Database\ConnectionInterface;

/**
 * Production. One atomic `nextval`, no application lock.
 *
 * A sequence is deliberately NOT transactional: a rolled-back create still
 * consumes its number, so references have gaps. That is the right trade — a
 * gap is invisible to everyone, while a lock held for the length of a
 * transaction would serialise every ticket creation in the system.
 */
final class PostgresTicketReferenceAllocator implements TicketReferenceAllocator
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function nextReference(): string
    {
        /** @var object{nextval: int|string} $row */
        $row = $this->db->selectOne("select nextval('ticket_reference_seq') as nextval");

        return TicketReference::format((int) $row->nextval);
    }
}
