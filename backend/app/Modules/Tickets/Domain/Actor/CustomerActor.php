<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Actor;

/**
 * A customer acting from their inbox, with no portal session.
 *
 * Distinct from PortalActor, and the distinction is not pedantry: the two
 * carry ids from DIFFERENT TABLES. A PortalActor's id is a portal account; a
 * customer who has never registered has no such row, and writing their
 * customer id into a column every reader resolves against portal accounts
 * would produce a name lookup that quietly returns nothing for ever.
 *
 * Distinct from SystemActor for a stronger reason. The rating is the one thing
 * on a ticket's history the customer wrote themselves, and attributing it to
 * the system would make the record say a machine decided how the customer
 * felt. AC-10 asks for the actor; this is the actor.
 */
final class CustomerActor extends Actor
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $displayName,
    ) {}

    public function kind(): string
    {
        return 'customer';
    }

    public function id(): ?string
    {
        return $this->customerId;
    }

    public function label(): string
    {
        return $this->displayName;
    }
}
