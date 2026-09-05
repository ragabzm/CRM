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

    /**
     * The customer that owns this phone number, if any.
     *
     * Compared on the normalised form, so a number given with a country code
     * and the same number typed locally find the same person. The web form
     * accepts a phone as readily as an email, and a customer who gave one on
     * Monday must not become a second record on Tuesday.
     */
    public function findIdByPhone(string $phone): ?string;

    /**
     * Creates a customer from nothing but a phone number and a display name.
     *
     * The phone counterpart of `createFromAddress`, flagged auto-created for
     * the same reason: nobody has confirmed any of it.
     *
     * @param  string  $createdVia  Which door they came through, e.g. `inbound_web_form`.
     * @return string  The new customer id.
     */
    public function createFromPhone(string $phone, string $displayName, string $createdVia): string;

    /**
     * The customer that owns this identifier, whatever kind it is.
     *
     * The general form the two above are special cases of. It exists because
     * WhatsApp made a third kind, and a `findIdByWhatsApp` beside the other
     * two would have made a fourth method the day a fourth kind arrives —
     * while the query underneath is the same one every time.
     *
     * @param  string  $kind  A `ContactKind` value.
     */
    public function findIdByIdentifier(string $kind, string $value): ?string;

    /**
     * The customer this identifier belongs to, creating or extending a record
     * as needed.
     *
     * ONE call that owns the whole identity question, because it has three
     * answers and a caller getting the third one wrong is how one person
     * becomes two:
     *
     *   1. This exact kind and value are on record — that customer.
     *   2. The same DIGITS are on record under a sibling kind — the same
     *      person, messaging from a channel we had not seen them use. The new
     *      identifier is added to them; a second customer is not created.
     *   3. Nothing matches — a new, auto-created record.
     *
     * Case 2 is the one that matters and the one that is easy to miss. A
     * customer who texts from a number and then messages from the same handset
     * on WhatsApp is one customer; treating them as two splits their history
     * in half, and neither half shows an agent what the other said.
     *
     * @param  string  $createdVia  Which door they came through, e.g. `inbound_whatsapp`.
     * @return array{id: string, created: bool}
     */
    public function resolveOrCreate(
        string $kind,
        string $value,
        string $displayName,
        string $createdVia,
    ): array;
}
