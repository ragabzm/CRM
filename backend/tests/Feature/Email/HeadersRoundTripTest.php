<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Customers\Domain\Customer;
use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Infrastructure\NullMailTransport;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The three headers that keep one conversation looking like one conversation.
 *
 * Our thread holds together on `ticket_id` regardless. These are for the
 * CUSTOMER's mail client: a message whose `In-Reply-To` names nothing it has
 * seen gets filed as a new conversation, and one problem becomes eight
 * unrelated emails in their inbox.
 *
 * Story 5.2 reads exactly these values back to correlate an inbound reply, so
 * their shape is a contract between two stories rather than an implementation
 * detail.
 */
final class HeadersRoundTripTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private Ticket $ticket;

    private NullMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // The channel is off by default, so nothing emails a real customer the
        // moment the application boots.
        $this->settings()->set('email.enabled', true, null);
        $this->settings()->set('email.domain', 'support.example.test', null);
        $this->settings()->set('email.acknowledgement.enabled', false, null);

        $this->transport = $this->app->make(NullMailTransport::class);
        $this->app->instance(MailTransport::class, $this->transport);

        $this->ticket = $this->makeTicket(['subject' => 'Invoice is wrong']);
        $this->giveCustomerAnAddress();
    }

    private function settings(): \App\Modules\Platform\Support\Settings\SettingsRegistry
    {
        return $this->app->make(\App\Modules\Platform\Support\Settings\SettingsRegistry::class);
    }

    private function giveCustomerAnAddress(string $address = 'hana@example.test'): void
    {
        DB::table('contact_identifiers')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'customer_id' => $this->ticket->customer_id,
            'kind' => 'email',
            'value' => $address,
            'value_normalised' => $address,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reply(string $body = 'We have credited it.'): string
    {
        $agent = $this->makeUser(Roles::AGENT);

        $message = $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $this->ticket->getKey(),
            MessageDirection::Outbound,
            $body,
        );

        return (string) $message->getKey();
    }

    public function test_a_reply_is_handed_to_the_transport(): void
    {
        $this->reply();

        $sent = $this->transport->lastSent();

        $this->assertNotNull($sent, 'The reply never reached the transport.');
        $this->assertSame('hana@example.test', $sent['to_address']);
    }

    public function test_the_first_message_carries_a_message_id(): void
    {
        $this->reply();

        $headers = $this->transport->lastSent()['headers'];

        // Angle-bracketed, per RFC 5322. A bare id is silently dropped by some
        // clients, which is the same as having no threading at all.
        $this->assertMatchesRegularExpression('/^<.+@support\.example\.test>$/', $headers['Message-ID']);
    }

    public function test_the_first_message_has_nothing_to_reply_to(): void
    {
        $this->reply();

        $headers = $this->transport->lastSent()['headers'];

        /*
         * Absent, not empty. An empty In-Reply-To is malformed, and some
         * clients discard the whole message rather than the header.
         */
        $this->assertArrayNotHasKey('In-Reply-To', $headers);
        $this->assertArrayNotHasKey('References', $headers);
    }

    public function test_the_second_message_replies_to_the_first(): void
    {
        $this->reply('First');
        $first = $this->transport->lastSent()['headers']['Message-ID'];

        $this->reply('Second');
        $second = $this->transport->lastSent()['headers'];

        $this->assertSame($first, $second['In-Reply-To']);
    }

    public function test_the_references_chain_accumulates(): void
    {
        $this->reply('First');
        $first = $this->transport->lastSent()['headers']['Message-ID'];

        $this->reply('Second');
        $second = $this->transport->lastSent()['headers']['Message-ID'];

        $this->reply('Third');
        $third = $this->transport->lastSent()['headers'];

        /*
         * The whole chain, oldest first — not just the parent. That is what
         * lets a client rebuild the thread when a message in the middle was
         * deleted or never arrived.
         */
        $this->assertSame("{$first} {$second}", $third['References']);
        $this->assertSame($second, $third['In-Reply-To']);
    }

    public function test_the_message_id_is_stored_so_a_reply_can_be_correlated(): void
    {
        $messageId = $this->reply();

        $stored = DB::table('ticket_messages')->where('id', $messageId)->value('email_message_id');

        // Story 5.2 looks an inbound reply up by this. Deriving it again later
        // would risk deriving something different from what actually went out.
        $this->assertSame($this->transport->lastSent()['headers']['Message-ID'], $stored);
    }

    public function test_the_subject_carries_the_ticket_reference(): void
    {
        $this->reply();

        // The fallback for every client that strips headers.
        $this->assertStringContainsString(
            "[#{$this->ticket->reference}]",
            $this->transport->lastSent()['subject'],
        );
    }

    public function test_an_internal_note_is_never_sent(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $this->ticket->getKey(),
            MessageDirection::Internal,
            'Second time this month — check the billing run.',
        );

        /*
         * The failure in this whole area that cannot be taken back: a note is a
         * colleague's private remark ABOUT the person it would be emailed to.
         */
        $this->assertNull($this->transport->lastSent());
    }

    public function test_an_inbound_message_is_never_echoed_back(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $this->ticket->getKey(),
            MessageDirection::Inbound,
            'My invoice is wrong.',
        );

        // Emailing the customer their own words back would look like a bug to
        // them, because it is one.
        $this->assertNull($this->transport->lastSent());
    }

    public function test_nothing_is_sent_while_the_channel_is_off(): void
    {
        $this->settings()->set('email.enabled', false, null);

        $this->reply();

        // The reply is still written — a switched-off channel must not refuse
        // an agent's words.
        $this->assertNull($this->transport->lastSent());
        $this->assertSame(1, DB::table('ticket_messages')->count());
    }

    public function test_a_customer_with_no_address_costs_nothing(): void
    {
        DB::table('contact_identifiers')->delete();

        $this->reply();

        // Nothing to send to is not a failure to send. Queueing a job that can
        // only fail would fill the log with noise.
        $this->assertNull($this->transport->lastSent());
        $this->assertSame(0, DB::table('mail_log')->count());
    }
}
