<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Reference;

use Illuminate\Database\ConnectionInterface;

/**
 * Tests and local SQLite only. SQLite has no sequences.
 *
 * Counts existing rows and adds one. That is a read-then-write and would race
 * under real concurrency — acceptable here and nowhere else, because the test
 * suite runs one transaction at a time. Production binds the Postgres
 * allocator; the binding is by driver, so this cannot be reached there.
 */
final class SqliteTicketReferenceAllocator implements TicketReferenceAllocator
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function nextReference(): string
    {
        /*
         * The highest reference already issued, not the row count: a deleted
         * ticket would otherwise let the next one reuse its number, and a
         * reference that points at two different tickets over time is worse
         * than a gap.
         */
        $highest = $this->db->table('tickets')->max('reference');

        $last = is_string($highest) ? (int) substr($highest, strlen(TicketReference::PREFIX)) : 0;

        return TicketReference::format($last + 1);
    }
}
