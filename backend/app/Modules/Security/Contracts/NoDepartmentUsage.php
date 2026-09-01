<?php

declare(strict_types=1);

namespace App\Modules\Security\Contracts;

/**
 * The null implementation, bound by Security itself.
 *
 * Answering 0 means "nothing known to be using it", which is the truth before
 * the Tickets module exists. Without a default, every Security test would need
 * the Tickets module present just to deactivate a department.
 */
final class NoDepartmentUsage implements DepartmentUsageProbe
{
    public function activeTicketCount(int $departmentId): int
    {
        return 0;
    }
}
