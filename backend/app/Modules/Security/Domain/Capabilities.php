<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain;

use ReflectionClass;

/**
 * The canonical capability list.
 *
 * Format is `resource.action`, always — never a `.scope` suffix. Scope is a
 * row-level question ("which tickets?") and belongs in a query, not in a
 * permission name: baking scope into the string produces a combinatorial
 * explosion of near-identical capabilities that nobody can audit, and it hides
 * the row rule somewhere no test looks.
 *
 * Tests iterate this class by reflection, so a capability that exists here but
 * is unseeded — or is referenced by a route but missing here — fails the build.
 */
final class Capabilities
{
    public const USER_MANAGE = 'user.manage';

    public const DEPARTMENT_MANAGE = 'department.manage';

    /**
     * Seeing the department list, which is not the same power as changing it.
     *
     * Every staff member needs it: it fills the filter on the customer list and
     * the picker on the customer form. Gating that behind department.manage
     * would mean only an administrator could file a customer under a team.
     */
    public const DEPARTMENT_READ = 'department.read';

    public const ROLE_READ = 'role.read';

    public const AUDIT_READ = 'audit.read';

    public const SETTING_MANAGE = 'setting.manage';

    public const TICKET_READ = 'ticket.read';

    public const TICKET_CREATE = 'ticket.create';

    public const TICKET_UPDATE = 'ticket.update';

    public const TICKET_REASSIGN = 'ticket.reassign';

    public const TICKET_CLOSE = 'ticket.close';

    /*
     * Lifecycle capabilities.
     *
     * Singular `ticket.*`, matching every other capability in this file. Story
     * 4.2 asked for plural `tickets.*`; mixing the two is how somebody writes
     * the wrong key months later and gets a silent refusal.
     */
    public const TICKET_ASSIGN = 'ticket.assign';

    /**
     * Taking a ticket somebody else is holding.
     *
     * Separate from TICKET_ASSIGN because they are different acts: picking up
     * unclaimed work is what an agent does all day, while pulling a ticket out
     * of a colleague's hands is a supervisor's call.
     */
    public const TICKET_REASSIGN_ANY = 'ticket.reassign_any';

    public const TICKET_CHANGE_STATUS = 'ticket.change_status';

    public const TICKET_CHANGE_DEPARTMENT = 'ticket.change_department';

    public const TICKET_RESOLVE = 'ticket.resolve';

    public const TICKET_REOPEN = 'ticket.reopen';

    public const CUSTOMER_READ = 'customer.read';

    public const CUSTOMER_MANAGE = 'customer.manage';

    /**
     * Every capability, read from the constants themselves so the list cannot
     * fall out of step with the class.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $values */
        $values = array_values((new ReflectionClass(self::class))->getConstants());

        return $values;
    }

    public static function exists(string $capability): bool
    {
        return in_array($capability, self::all(), true);
    }
}
