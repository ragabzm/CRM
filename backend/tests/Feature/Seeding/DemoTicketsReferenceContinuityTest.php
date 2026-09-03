<?php

declare(strict_types=1);

namespace Tests\Feature\Seeding;

use App\Modules\Customers\Domain\Customer;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCustomersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The first ticket after a seed continues the sequence.
 *
 * A seeder that hard-coded `TKT-000001` would look fine until the application
 * allocated its own first reference and collided with it — and the collision
 * surfaces as a failed ticket creation for a real person, long after anyone
 * would connect it to the seed.
 *
 * The fix is not to check for collisions. It is for the seeder never to invent
 * a reference at all, which is what `CreateTicket` guarantees by taking one
 * from `TicketReferenceAllocator`. This test proves the seeder actually goes
 * through that path rather than around it.
 */
final class DemoTicketsReferenceContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_next_reference_is_beyond_every_seeded_one(): void
    {
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $highestSeeded = self::highestReferenceNumber();

        $this->assertGreaterThan(0, $highestSeeded, 'Nothing was seeded, so this proves nothing.');

        $customer = DemoCustomersSeeder::findByEmail('layla.haddad@example.test');

        $this->assertNotNull($customer);

        $ticket = app(CreateTicket::class)->handle(
            Actor::system('continuity check'),
            new CreateTicketInput(
                subject: 'Raised by the application after the seed',
                description: 'Checks the allocator picked up where the seed left off.',
                customerId: (string) $customer->getKey(),
                channel: TicketChannel::Agent,
            ),
        );

        $this->assertGreaterThan(
            $highestSeeded,
            self::numberOf($ticket->reference),
            "The application reissued a reference the seed had already used ({$ticket->reference}).",
        );
    }

    public function test_no_seeder_writes_a_reference_of_its_own(): void
    {
        /*
         * The static half of the same guarantee. A reference-shaped literal in
         * a seeder is the bug this test exists to catch, and it catches it
         * before anyone has to run a database to find out.
         */
        foreach (glob(database_path('seeders/Demo*Seeder.php')) ?: [] as $file) {
            $source = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/[\'"]TKT-\d+[\'"]/',
                $source,
                basename($file).' hard-codes a ticket reference.',
            );

            $this->assertDoesNotMatchRegularExpression(
                '/[\'"]C-['.preg_quote(Customer::REFERENCE_ALPHABET, '/').']{8}[\'"]/',
                $source,
                basename($file).' hard-codes a customer reference.',
            );
        }
    }

    public function test_a_customer_reference_is_minted_not_written(): void
    {
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        foreach (Customer::query()->pluck('reference') as $reference) {
            $this->assertMatchesRegularExpression(
                '/^C-['.preg_quote(Customer::REFERENCE_ALPHABET, '/').']{8}$/',
                (string) $reference,
                "Seeded reference [{$reference}] did not come from Customer::mintReference().",
            );
        }
    }

    private static function highestReferenceNumber(): int
    {
        return (int) Ticket::query()
            ->pluck('reference')
            ->map(static fn (string $reference): int => self::numberOf($reference))
            ->max();
    }

    private static function numberOf(string $reference): int
    {
        preg_match('/(\d+)$/', $reference, $matches);

        return (int) ($matches[1] ?? 0);
    }
}
