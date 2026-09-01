<?php

declare(strict_types=1);

namespace App\Modules\Security\Contracts;

/**
 * Asks whether anything still depends on a department.
 *
 * DEPENDENCY DIRECTION, and why this interface lives HERE:
 *
 * Security is T1 and Tickets is T3, so Security may not import from Tickets —
 * the tier rule only permits dependencies downward. The port is therefore
 * declared by the module that NEEDS the answer (Security) and implemented by
 * the module that HAS it (Tickets), which depends downward on this interface.
 * That is the correct inversion: the higher tier implements the lower tier's
 * contract.
 *
 * The story plan proposed the opposite — a contract published from Tickets and
 * injected into Security — which deptrac rejects.
 *
 * The default binding answers 0, so Security works before the Tickets module
 * has a schema at all.
 */
interface DepartmentUsageProbe
{
    /**
     * How many still-open items would be stranded by deactivating this
     * department. Zero means deactivation is safe.
     */
    public function activeTicketCount(int $departmentId): int;
}
