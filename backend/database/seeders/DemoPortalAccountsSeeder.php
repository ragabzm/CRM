<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Portal\Domain\PortalAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Two customers who can sign in to the portal.
 *
 * Two, not six, on purpose: most customers of a support desk never register,
 * and a dataset where every customer has a login hides the case the product
 * spends most of its time in — a person who exists only as an email address.
 *
 * Both are linked to a seeded customer. An account with a null `customer_id`
 * is a real state (someone registered before anyone matched them to a record),
 * but it is not this seeder's job to manufacture one: it would make every
 * portal screen in the demo dataset show an empty list.
 */
final class DemoPortalAccountsSeeder extends Seeder
{
    /** @var list<array{string, string}> customer email (natural key), account name */
    public const ACCOUNTS = [
        ['layla.haddad@example.test', 'Layla Haddad'],
        ['omar.farouk@example.test', 'Omar Farouk'],
    ];

    public function run(): void
    {
        if (! DemoEnvironment::allows($this->command, self::class)) {
            return;
        }

        DemoEnvironment::needs($this, DemoCustomersSeeder::class, static fn (): bool => \App\Modules\Customers\Domain\Customer::query()->exists());

        $seeded = 0;

        foreach (self::ACCOUNTS as [$email, $name]) {
            $customer = DemoCustomersSeeder::findByEmail($email);

            if ($customer === null) {
                continue;
            }

            PortalAccount::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(DevAccountsSeeder::PASSWORD),
                    // Verified, so the demo does not open on a wall telling
                    // you to check an inbox that does not exist.
                    'email_verified_at' => now(),
                    'customer_id' => $customer->getKey(),
                    'preferred_locale' => $customer->preferred_locale ?? 'en',
                ],
            );

            $seeded++;
        }

        $this->command?->info("Seeded {$seeded} demo portal accounts (same password as the staff accounts).");
    }
}
