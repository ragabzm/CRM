<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Query;

use App\Modules\Security\Contracts\DepartmentUsageProbe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets' answer to Security's question.
 *
 * Implements an interface declared in Security (T1) because the dependency may
 * only run downward: Tickets is T3 and may depend on Security, never the
 * reverse. Security asks "is anything still using this department?" without
 * knowing what a ticket is.
 *
 * Returns 0 while the tickets table does not exist yet (Story 4.1 creates it),
 * so departments remain deactivatable in the meantime.
 */
final class DepartmentTicketUsage implements DepartmentUsageProbe
{
    /** Statuses that still need someone to act. */
    public const ACTIVE_STATUSES = ['open', 'pending'];

    public function activeTicketCount(int $departmentId): int
    {
        if (! Schema::hasTable('tickets')) {
            return 0;
        }

        return DB::table('tickets')
            ->where('department_id', $departmentId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();
    }
}
