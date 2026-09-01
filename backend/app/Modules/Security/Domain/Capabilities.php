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

    public const ROLE_READ = 'role.read';

    public const AUDIT_READ = 'audit.read';

    public const SETTING_MANAGE = 'setting.manage';

    public const TICKET_READ = 'ticket.read';

    public const TICKET_CREATE = 'ticket.create';

    public const TICKET_UPDATE = 'ticket.update';

    public const TICKET_REASSIGN = 'ticket.reassign';

    public const TICKET_CLOSE = 'ticket.close';

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
