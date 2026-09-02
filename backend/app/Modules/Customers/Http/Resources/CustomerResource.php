<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Domain\ContactIdentifier;
use App\Modules\Customers\Domain\Customer;

/**
 * The public shape of a customer.
 *
 * Built explicitly, never by serialising the model: a hidden-attribute list is
 * a denylist, and a denylist is one migration away from publishing a column
 * nobody remembered to hide.
 *
 * There is no organisation, company or account field, and there is not going to
 * be one — see tests/Architecture/NoOrganisationFieldTest.php.
 */
final class CustomerResource
{
    /**
     * @return array<string, mixed>
     */
    public static function detail(Customer $customer, ?string $departmentName): array
    {
        return [
            ...self::summary($customer, $departmentName),
            'notes' => $customer->notes,
            'created_at' => $customer->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Customer $customer, ?string $departmentName): array
    {
        return [
            'id' => (string) $customer->getKey(),
            'reference' => $customer->reference,
            'full_name' => $customer->full_name,
            'department' => [
                'id' => (int) $customer->department_id,
                'name' => $departmentName,
            ],
            'state' => $customer->state,
            'preferred_channel' => $customer->preferred_channel,
            'identifiers' => $customer->identifiers
                ->map(static fn (ContactIdentifier $identifier): array => [
                    'id' => (string) $identifier->getKey(),
                    'kind' => $identifier->kind,
                    // The raw value, not the normalised one. The normalised
                    // form exists to compare with, never to show — nobody wants
                    // their phone number handed back with the punctuation
                    // stripped out.
                    'value' => $identifier->value,
                    'is_primary' => $identifier->is_primary,
                ])
                ->values()
                ->all(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
            'deactivated_at' => $customer->deactivated_at?->toIso8601String(),
        ];
    }
}
