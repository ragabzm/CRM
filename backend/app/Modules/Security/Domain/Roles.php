<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain;

/**
 * The four roles. Fixed, seeded, and not editable at runtime.
 *
 * There is no role builder and no admin UI over this list on purpose. A system
 * where roles can be invented at runtime is a system where nobody can answer
 * "who can do this?" without querying production — and where a permission
 * matrix drifts silently between environments. Changing this list is a
 * migration and a story, which is the point.
 */
final class Roles
{
    public const ADMINISTRATOR = 'administrator';

    public const SUPERVISOR = 'supervisor';

    public const AGENT = 'agent';

    public const CUSTOMER = 'customer';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ADMINISTRATOR, self::SUPERVISOR, self::AGENT, self::CUSTOMER];
    }
}
