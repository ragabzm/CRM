<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain\Directory;

use App\Modules\Customers\Contracts\CustomerDirectory;
use App\Modules\Customers\Domain\ContactIdentifier;
use App\Modules\Customers\Domain\Customer;
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

    public function createFromAddress(string $email, string $displayName, string $createdVia): string
    {
        $normalised = strtolower(trim($email));

        $customer = new Customer([
            'reference' => Customer::mintReference(),
            // Falls back to the address: a blank name leaves an agent with a
            // nameless row and no way to tell two auto-created customers apart.
            'full_name' => trim($displayName) !== '' ? trim($displayName) : $email,
            'state' => 'active',
        ]);

        $customer->setAttribute('id', (string) Str::ulid());
        $customer->setAttribute('auto_created', true);
        $customer->setAttribute('created_via', $createdVia);
        $customer->save();

        $identifier = new ContactIdentifier([
            'customer_id' => $customer->getKey(),
            'kind' => 'email',
            'value' => $email,
            'value_normalised' => $normalised,
            'is_primary' => true,
        ]);

        $identifier->setAttribute('id', (string) Str::ulid());
        $identifier->save();

        return (string) $customer->getKey();
    }
}
