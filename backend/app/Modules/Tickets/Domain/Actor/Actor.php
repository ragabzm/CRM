<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Actor;

/**
 * Who is doing this.
 *
 * Passed explicitly into every command, never read from ambient state. Domain
 * code that called `auth()` would be unusable from a queue worker, a console
 * command or a test — and the auto-close job in a later story is exactly a
 * caller with no session at all.
 *
 * Making it a parameter also means the answer to "who changed this?" is
 * something the caller had to decide and can be reviewed, rather than whatever
 * happened to be in the container.
 */
abstract class Actor
{
    /** `staff` | `portal` | `system` — stored on the ticket and every event. */
    abstract public function kind(): string;

    /** Null for the system, which has no identity to record. */
    abstract public function id(): ?string;

    /** A human-readable name for the event trail. */
    abstract public function label(): string;

    /** Why the system acted. Null for a person, who needs no justification. */
    public function reason(): ?string
    {
        return null;
    }

    public static function staff(string $userId, string $displayName): StaffActor
    {
        return new StaffActor($userId, $displayName);
    }

    public static function portal(string $portalAccountId, string $displayName): PortalActor
    {
        return new PortalActor($portalAccountId, $displayName);
    }

    /**
     * A customer with no portal session — an emailed invitation they tapped.
     *
     * The id is a CUSTOMER id, not a portal account id. See CustomerActor.
     */
    public static function customer(string $customerId, string $displayName): CustomerActor
    {
        return new CustomerActor($customerId, $displayName);
    }

    public static function system(string $reason): SystemActor
    {
        return new SystemActor($reason);
    }

    /**
     * The chatbot, which is the single thing in this product that reaches a
     * customer without a person having read it.
     *
     * Still a SYSTEM actor — there is no AI actor kind and there is not going
     * to be one — but labelled so that a customer, and the colleague who picks
     * the ticket up afterwards, can tell it apart from a colleague's own
     * reply. It never presents itself as a person.
     */
    public static function chatbot(string $reason): SystemActor
    {
        return new SystemActor($reason, self::CHATBOT);
    }

    /**
     * What the chatbot is called in a transcript.
     *
     * A stable token rather than a translated word: the transcript is written
     * once and read in both languages, and a stored "Assistant" would be
     * English for ever on an Arabic desk.
     */
    public const CHATBOT = 'chatbot';
}
