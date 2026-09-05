<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain\Directory;

use App\Modules\Customers\Contracts\CustomerDirectory;
use App\Modules\Customers\Domain\ContactIdentifier;
use App\Modules\Customers\Domain\IdentifierNormaliser;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\ContactKind;
use Illuminate\Support\Str;

/**
 * The Customers module's own answer to "who is this address?".
 *
 * Lives here, with the models and the reference format, so that a change to any
 * of them is a change in one module rather than a silent break in another.
 */
final class EloquentCustomerDirectory implements CustomerDirectory
{
    public function findIdByEmail(string $email): ?string
    {
        $normalised = strtolower(trim($email));

        $id = ContactIdentifier::query()
            ->where('kind', 'email')
            /*
             * The normalised column, lowered again at compare time. Matching
             * the raw value would create a second customer for the same person
             * the first time they capitalised their own address.
             */
            ->whereRaw('lower(value_normalised) = ?', [$normalised])
            ->value('customer_id');

        return $id === null ? null : (string) $id;
    }

    public function findIdByPhone(string $phone): ?string
    {
        return $this->byNormalised(ContactKind::Phone, $phone);
    }

    public function findIdByIdentifier(string $kind, string $value): ?string
    {
        $contactKind = ContactKind::tryFrom($kind);

        if ($contactKind === null) {
            return null;
        }

        return $contactKind === ContactKind::Email
            ? $this->findIdByEmail($value)
            : $this->byNormalised($contactKind, $value);
    }

    public function resolveOrCreate(
        string $kind,
        string $value,
        string $displayName,
        string $createdVia,
    ): array {
        $contactKind = ContactKind::from($kind);

        $normalised = $contactKind === ContactKind::Email
            ? strtolower(trim($value))
            : IdentifierNormaliser::normalise($contactKind, $value);

        // 1. This exact identifier.
        $exact = $this->findIdByIdentifier($kind, $value);

        if ($exact !== null) {
            return ['id' => $exact, 'created' => false];
        }

        // 2. The same digits under a sibling kind — the same person on a
        //    channel we had not seen them use.
        $sibling = $this->bySiblingKind($contactKind, $normalised);

        if ($sibling !== null) {
            $this->attach($sibling, $contactKind, $value, $normalised);

            return ['id' => $sibling, 'created' => false];
        }

        // 3. Somebody new.
        return [
            'id' => $this->create($contactKind, $value, $normalised, $displayName, $createdVia),
            'created' => true,
        ];
    }

    /**
     * The customer these same digits belong to, under any OTHER phone-like
     * kind.
     *
     * Email is excluded from both sides: an address is not a number, and two
     * different people never share one by accident the way a phone and a
     * WhatsApp number are the same handset.
     */
    private function bySiblingKind(ContactKind $kind, string $normalised): ?string
    {
        if ($kind === ContactKind::Email || $normalised === '') {
            return null;
        }

        $siblings = array_values(array_filter(
            ContactKind::cases(),
            static fn (ContactKind $case): bool => $case !== ContactKind::Email && $case !== $kind,
        ));

        $id = ContactIdentifier::query()
            ->whereIn('kind', array_map(static fn (ContactKind $c): string => $c->value, $siblings))
            ->where('value_normalised', $normalised)
            ->value('customer_id');

        return $id === null ? null : (string) $id;
    }

    /** Adds an identifier to a customer who already exists. */
    private function attach(string $customerId, ContactKind $kind, string $value, string $normalised): void
    {
        $identifier = new ContactIdentifier([
            'customer_id' => $customerId,
            'kind' => $kind->value,
            'value' => $value,
            'value_normalised' => $normalised,
            /*
             * Not primary. They already had a way we reach them; this is
             * another one, and quietly promoting it would change where an
             * agent's reply goes without anybody deciding.
             */
            'is_primary' => false,
        ]);

        $identifier->setAttribute('id', (string) Str::ulid());
        $identifier->save();
    }

    /**
     * Matched on the normalised form, within one kind.
     *
     * WITHIN one kind, deliberately: a phone number and a WhatsApp number
     * normalise identically, and they are often the same digits on the same
     * record. Searching across kinds would make a WhatsApp message resolve
     * through a phone identifier and lose the fact that they wrote from
     * WhatsApp.
     */
    private function byNormalised(ContactKind $kind, string $value): ?string
    {
        $normalised = IdentifierNormaliser::normalise($kind, $value);

        if ($normalised === '') {
            return null;
        }

        $id = ContactIdentifier::query()
            ->where('kind', $kind->value)
            ->where('value_normalised', $normalised)
            ->value('customer_id');

        return $id === null ? null : (string) $id;
    }

    public function createFromPhone(string $phone, string $displayName, string $createdVia): string
    {
        return $this->create(
            ContactKind::Phone,
            $phone,
            IdentifierNormaliser::normalise(ContactKind::Phone, $phone),
            $displayName,
            $createdVia,
        );
    }

    public function createFromAddress(string $email, string $displayName, string $createdVia): string
    {
        return $this->create(
            ContactKind::Email,
            $email,
            strtolower(trim($email)),
            $displayName,
            $createdVia,
        );
    }

    /**
     * One customer and one identifier, whatever the identifier is.
     *
     * Written once because the two halves that differ — the kind and the
     * normalised form — are the only two that differ. A second copy of this
     * for phones is how the auto-created flag or the reference format ends up
     * set on emails and forgotten on numbers.
     */
    private function create(
        ContactKind $kind,
        string $value,
        string $normalised,
        string $displayName,
        string $createdVia,
    ): string {
        $customer = new Customer([
            'reference' => Customer::mintReference(),
            // Falls back to the address: a blank name leaves an agent with a
            // nameless row and no way to tell two auto-created customers apart.
            'full_name' => trim($displayName) !== '' ? trim($displayName) : $value,
            'state' => 'active',
        ]);

        $customer->setAttribute('id', (string) Str::ulid());
        $customer->setAttribute('auto_created', true);
        $customer->setAttribute('created_via', $createdVia);
        $customer->save();

        $identifier = new ContactIdentifier([
            'customer_id' => $customer->getKey(),
            'kind' => $kind->value,
            'value' => $value,
            'value_normalised' => $normalised,
            'is_primary' => true,
        ]);

        $identifier->setAttribute('id', (string) Str::ulid());
        $identifier->save();

        return (string) $customer->getKey();
    }
}
