<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

/**
 * How another module finds or creates a customer, without knowing how one is
 * stored.
 *
 * The Email module needs exactly two things when a stranger writes in: is this
 * person already known, and if not, make a record. Doing that with raw inserts
 * meant Email knew the shape of two of Customers' tables, the reference format,
 * and the address-normalisation rule — three things that are Customers'
 * business and that would break Email silently the day any of them changed.
 *
 * Primitives only, so no caller learns a domain type.
 */
interface CustomerDirectory
{
    /**
     * The customer that owns this email address, if any.
     *
     * Matches every address on record, not just a primary one: people write
     * from work on Monday and from their phone on Saturday, and treating those
     * as two customers splits one person's history in half.
     */
    public function findIdByEmail(string $email): ?string;

    /**
     * Creates a customer from nothing but an email and a display name.
     *
     * Flagged as auto-created, because the record is thinner than one a person
     * filled in — the name is whatever was in a From header and nobody has
     * confirmed any of it.
     *
     * @param  string  $createdVia  Which door they came through, e.g. `inbound_email`.
     * @return string  The new customer id.
     */
    public function createFromAddress(string $email, string $displayName, string $createdVia): string;
}
