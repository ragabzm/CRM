<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Reference;

/**
 * Hands out the next human-readable ticket reference.
 *
 * A port, because the only correct implementation depends on the database:
 * Postgres has an atomic sequence, SQLite does not, and getting this wrong
 * means two tickets sharing a reference — which an agent discovers by quoting
 * the wrong one to a customer.
 */
interface TicketReferenceAllocator
{
    /** e.g. `TKT-000123`. */
    public function nextReference(): string;
}
