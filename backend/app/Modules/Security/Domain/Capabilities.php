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

    /**
     * Reading the mail nobody could turn into a ticket.
     *
     * Administrator-only: quarantined messages carry the RAW source of a
     * customer's email — their words, their address, and whatever they
     * attached — for mail that failed before any of the usual access rules
     * could apply to it.
     */
    public const QUARANTINE_VIEW = 'quarantine.view';

    /**
     * Feeding a quarantined message back through intake.
     *
     * Separate from viewing, because it WRITES: a replay can open a ticket and
     * email a customer. Somebody diagnosing a parser bug needs to read; only
     * somebody deciding to act needs this.
     */
    public const QUARANTINE_REPLAY = 'quarantine.replay';

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

    /**
     * Marking a ticket as going wrong.
     *
     * Its own capability rather than folded into `ticket.update`, because it
     * is the one ticket action whose audience is other PEOPLE: it puts an
     * alert in front of every supervisor in the department. An agent should be
     * able to raise a hand — they are the one who can see the trouble — but it
     * is a distinct thing from editing a field, and a deployment may want to
     * say so.
     */
    public const TICKET_ESCALATE = 'ticket.escalate';

    public const TICKET_CHANGE_STATUS = 'ticket.change_status';

    public const TICKET_CHANGE_DEPARTMENT = 'ticket.change_department';

    public const TICKET_RESOLVE = 'ticket.resolve';

    public const TICKET_REOPEN = 'ticket.reopen';

    public const CUSTOMER_READ = 'customer.read';

    public const CUSTOMER_MANAGE = 'customer.manage';

    /**
     * Turning a channel on or off and binding it to a department.
     *
     * Separate from `setting.manage` because it is not a setting: disabling a
     * channel stops customers reaching the desk through it, which is an
     * operational decision with a visible consequence, not a preference.
     */
    public const CHANNEL_MANAGE = 'channel.manage';

    /** Reading the knowledge base, including articles not published to customers. */
    public const KNOWLEDGE_VIEW = 'knowledge.view';

    /** Writing and organising articles and their categories. */
    public const KNOWLEDGE_MANAGE = 'knowledge.manage';

    /**
     * Putting an article in front of people, and taking it back out.
     *
     * Separate from `knowledge.manage` because publishing is the irreversible
     * half: once an article has been published it can never be deleted, only
     * archived, and a customer may already be holding the link. Writing a draft
     * and deciding it is ready are two different decisions.
     */
    public const KNOWLEDGE_PUBLISH = 'knowledge.publish';

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
