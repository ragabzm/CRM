<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Customers\Domain\ContactIdentifier;
use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\CustomerNote;
use App\Modules\Customers\Domain\CustomerState;
use App\Modules\Customers\Domain\IdentifierNormaliser;
use App\Modules\Security\Domain\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Six people, spread across the three departments.
 *
 * Not six copies of one person: the set deliberately contains an inactive
 * record, both preferred channels, both locales, and two customers who share
 * a department. A demo dataset where every row is the happy path is a dataset
 * that never shows you the screen you actually need to look at.
 *
 * The natural key is the primary EMAIL identifier, not the reference.
 * References are minted randomly by `Customer::mintReference()` and differ on
 * every fresh database — keying on one would make the seeder create a seventh
 * Layla on the second run.
 */
final class DemoCustomersSeeder extends Seeder
{
    /**
     * @var list<array{
     *   name: string, email: string, phone: string, department: string,
     *   state: string, channel: string, locale: string
     * }>
     */
    public const CUSTOMERS = [
        ['name' => 'Layla Haddad',  'email' => 'layla.haddad@example.test',  'phone' => '+20 100 555 0101', 'department' => 'Support', 'state' => 'active',   'channel' => 'email', 'locale' => 'ar'],
        ['name' => 'Omar Farouk',   'email' => 'omar.farouk@example.test',   'phone' => '+20 100 555 0102', 'department' => 'Sales',   'state' => 'active',   'channel' => 'email', 'locale' => 'ar'],
        ['name' => 'Sarah Nasser',  'email' => 'sarah.nasser@example.test',  'phone' => '+20 100 555 0103', 'department' => 'Billing', 'state' => 'active',   'channel' => 'phone', 'locale' => 'en'],
        ['name' => 'Yusuf Salim',   'email' => 'yusuf.salim@example.test',   'phone' => '+20 100 555 0104', 'department' => 'Support', 'state' => 'active',   'channel' => 'email', 'locale' => 'ar'],
        // One deactivated record. Every screen that lists customers has to
        // decide what to do with it, and a seed without one never asks.
        ['name' => 'Nadia Kassem',  'email' => 'nadia.kassem@example.test',  'phone' => '+20 100 555 0105', 'department' => 'Billing', 'state' => 'inactive', 'channel' => 'phone', 'locale' => 'en'],
        ['name' => 'Karim Zahran',  'email' => 'karim.zahran@example.test',  'phone' => '+20 100 555 0106', 'department' => 'Sales',   'state' => 'active',   'channel' => 'email', 'locale' => 'en'],
    ];

    /** @var list<array{string, string}> customer email, note body */
    private const NOTES = [
        ['layla.haddad@example.test', 'Prefers Arabic. Calls from a work number that is not on the record.'],
        ['sarah.nasser@example.test', 'Finance contact for the account; copy her on anything about invoices.'],
    ];

    public function run(): void
    {
        if (! DemoEnvironment::allows($this->command, self::class)) {
            return;
        }

        DemoEnvironment::needs($this, DemoDepartmentsSeeder::class, static fn (): bool => Department::query()->exists());
        DemoEnvironment::needs($this, DemoAgentsSeeder::class, static fn (): bool => User::query()->whereNotNull('department_id')->exists());

        $departments = Department::query()->pluck('id', 'name');

        foreach (self::CUSTOMERS as $person) {
            $this->upsertCustomer($person, (int) $departments[$person['department']]);
        }

        $this->seedNotes();

        $this->command?->info(sprintf(
            'Seeded %d demo customers with %d notes.',
            count(self::CUSTOMERS),
            count(self::NOTES),
        ));
    }

    /**
     * @param  array{name: string, email: string, phone: string, department: string, state: string, channel: string, locale: string}  $person
     */
    private function upsertCustomer(array $person, int $departmentId): void
    {
        $existing = self::findByEmail($person['email']);

        if ($existing !== null) {
            // Found by their email identifier, so this customer already
            // exists. Their attributes are refreshed; the reference is left
            // exactly as minted, because it is quoted over the phone and on
            // paper and must not change under someone.
            $existing->forceFill([
                'full_name' => $person['name'],
                'department_id' => $departmentId,
                'state' => $person['state'],
                'preferred_channel' => $person['channel'],
                'preferred_locale' => $person['locale'],
            ])->save();

            $this->upsertIdentifiers($existing, $person);

            return;
        }

        $customer = new Customer;

        $customer->forceFill([
            'reference' => Customer::mintReference(),
            'full_name' => $person['name'],
            'department_id' => $departmentId,
            'state' => $person['state'],
            'preferred_channel' => $person['channel'],
            'preferred_locale' => $person['locale'],
            'deactivated_at' => $person['state'] === CustomerState::Inactive->value ? now() : null,
        ])->save();

        $this->upsertIdentifiers($customer, $person);
    }

    /**
     * @param  array{email: string, phone: string}  $person
     */
    private function upsertIdentifiers(Customer $customer, array $person): void
    {
        foreach ([[ContactKind::Email, $person['email']], [ContactKind::Phone, $person['phone']]] as [$kind, $value]) {
            ContactIdentifier::query()->updateOrCreate(
                [
                    'customer_id' => $customer->getKey(),
                    'kind' => $kind->value,
                ],
                [
                    'value' => $value,
                    'value_normalised' => IdentifierNormaliser::normalise($kind, $value),
                    // Exactly one primary per kind, and each customer here has
                    // exactly one of each — so the constraint is satisfied by
                    // construction rather than by hoping.
                    'is_primary' => true,
                ],
            );
        }
    }

    private function seedNotes(): void
    {
        // Whoever is available to have written them. A note carries its
        // author's name as a column, so a seeded note still reads correctly
        // even after that account is gone.
        $author = User::query()->where('email', 'super@ragab.test')->first()
            ?? User::query()->orderBy('id')->first();

        if ($author === null) {
            return;
        }

        foreach (self::NOTES as [$email, $body]) {
            $customer = self::findByEmail($email);

            if ($customer === null) {
                continue;
            }

            CustomerNote::query()->updateOrCreate(
                [
                    // Body is part of the key: two different notes on one
                    // customer by one author are two notes, and re-running
                    // must not turn them into one.
                    'customer_id' => $customer->getKey(),
                    'author_id' => $author->getKey(),
                    'body' => $body,
                ],
                ['author_name' => $author->name],
            );
        }
    }

    /**
     * The natural key, resolved through the normalised value so it matches the
     * way the rest of the product looks a customer up.
     */
    public static function findByEmail(string $email): ?Customer
    {
        $normalised = IdentifierNormaliser::normalise(ContactKind::Email, $email);

        $customerId = DB::table('contact_identifiers')
            ->where('kind', ContactKind::Email->value)
            ->where('value_normalised', $normalised)
            ->value('customer_id');

        return $customerId === null
            ? null
            : Customer::query()->find($customerId);
    }
}
