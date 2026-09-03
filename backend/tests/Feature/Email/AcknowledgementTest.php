<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Infrastructure\NullMailTransport;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Telling the customer their request was received.
 *
 * The silence between raising a request and an agent picking it up is where
 * people give up and phone instead, or raise the same ticket twice. The
 * acknowledgement is short, carries the reference they will need if they do
 * call, and is in their own language — a reflexive English auto-reply to
 * somebody who wrote in Arabic reads as "we did not read your message".
 */
final class AcknowledgementTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private NullMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->settings()->set('email.enabled', true, null);
        $this->settings()->set('email.acknowledgement.enabled', true, null);

        $this->transport = $this->app->make(NullMailTransport::class);
        $this->app->instance(MailTransport::class, $this->transport);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    private function openTicket(?string $locale = null): Ticket
    {
        $customerId = $this->makeCustomer();

        DB::table('customers')->where('id', $customerId)->update(['preferred_locale' => $locale]);

        DB::table('contact_identifiers')->insert([
            'id' => (string) Str::ulid(),
            'customer_id' => $customerId,
            'kind' => 'email',
            'value' => 'hana@example.test',
            'value_normalised' => 'hana@example.test',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $agent = $this->makeUser(Roles::AGENT);

        return $this->app->make(CreateTicket::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            new CreateTicketInput(
                subject: 'Invoice is wrong',
                description: 'Charged twice.',
                customerId: $customerId,
                channel: TicketChannel::Agent,
            ),
        );
    }

    public function test_opening_a_ticket_acknowledges_it(): void
    {
        $this->openTicket();

        $this->assertNotNull($this->transport->lastSent());
    }

    public function test_the_acknowledgement_carries_the_reference(): void
    {
        $ticket = $this->openTicket();
        $sent = $this->transport->lastSent();

        // In the subject AND the body: a customer forwarding it to a colleague
        // loses the subject line more often than they lose the text.
        $this->assertStringContainsString($ticket->reference, $sent['subject']);
        $this->assertStringContainsString($ticket->reference, $sent['body']);
    }

    public function test_it_speaks_the_customers_language(): void
    {
        $this->openTicket(locale: 'ar');

        $sent = $this->transport->lastSent();

        $this->assertSame('ar', $sent['locale']);
        $this->assertStringContainsString('شكرًا', $sent['body']);
    }

    public function test_it_falls_back_to_english_when_nobody_has_said(): void
    {
        $this->openTicket(locale: null);

        $sent = $this->transport->lastSent();

        // A fallback, not a preference the customer expressed.
        $this->assertSame('en', $sent['locale']);
        $this->assertStringContainsString('Thank you', $sent['body']);
    }

    public function test_an_administrator_can_switch_it_off(): void
    {
        $this->settings()->set('email.acknowledgement.enabled', false, null);

        $this->openTicket();

        $this->assertNull($this->transport->lastSent());
    }

    public function test_a_ticket_is_created_even_when_the_channel_is_off(): void
    {
        $this->settings()->set('email.enabled', false, null);

        $ticket = $this->openTicket();

        /*
         * The property that matters most in this whole story: a provider being
         * unreachable must never stop somebody raising a ticket. The work is
         * recorded; the email is a separate promise.
         */
        $this->assertNotNull(Ticket::query()->find($ticket->getKey()));
        $this->assertNull($this->transport->lastSent());
    }

    public function test_a_customer_with_no_address_still_gets_a_ticket(): void
    {
        $customerId = $this->makeCustomer();
        $agent = $this->makeUser(Roles::AGENT);

        $ticket = $this->app->make(CreateTicket::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            new CreateTicketInput('Phoned in', 'Body', $customerId, TicketChannel::Agent),
        );

        $this->assertNotNull(Ticket::query()->find($ticket->getKey()));
        $this->assertNull($this->transport->lastSent());
    }

    public function test_a_broken_provider_does_not_break_ticket_creation(): void
    {
        $this->app->instance(MailTransport::class, new class implements MailTransport
        {
            public function send(string $a, string $b, string $c, string $d, array $e, string $f): void
            {
                throw \App\Modules\Email\Contracts\MailTransportFailure::temporary('Connection refused');
            }

            public function name(): string
            {
                return 'broken';
            }
        });

        // The job throws, the queue would retry, and the ticket is untouched.
        try {
            $ticket = $this->openTicket();
            $this->assertNotNull(Ticket::query()->find($ticket->getKey()));
        } catch (\Throwable $e) {
            $this->fail('Ticket creation must not fail because email did: '.$e->getMessage());
        }
    }
}
