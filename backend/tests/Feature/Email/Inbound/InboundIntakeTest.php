<?php

declare(strict_types=1);

namespace Tests\Feature\Email\Inbound;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Infrastructure\NullMailTransport;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A customer emails support and something happens.
 *
 * The whole story in one path: bytes arrive, and either a ticket exists that
 * did not before, or a reply appears on one that did — with no account, no
 * portal, and no form.
 */
final class InboundIntakeTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private const SECRET = 'a-shared-secret';

    private NullMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->settings()->set('email.inbound.enabled', true, null);
        $this->settings()->set('email.inbound.webhook_secret', self::SECRET, null);
        $this->settings()->set('email.enabled', true, null);
        $this->settings()->set('email.domain', 'support.example.test', null);

        $this->transport = $this->app->make(NullMailTransport::class);
        $this->app->instance(MailTransport::class, $this->transport);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    /** A minimal but genuinely well-formed RFC 5322 message. */
    private function mail(array $headers = [], string $body = 'My invoice is wrong.'): string
    {
        $lines = array_merge([
            'From: Hana Yousef <hana@example.test>',
            'To: support@example.test',
            'Subject: Invoice trouble',
            'Message-ID: <'.Str::ulid().'@mail.example.test>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
        ], $headers);

        return implode("\r\n", $lines)."\r\n\r\n".$body;
    }

    private function deliver(string $raw, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Webhook-Secret', self::SECRET)
            ->postJson('/api/v1/inbound/email', ['raw' => $raw, ...$payload]);
    }

    public function test_an_email_from_a_stranger_becomes_a_ticket(): void
    {
        $this->deliver($this->mail())->assertOk()->assertJsonPath('status', 'accepted');

        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame('Invoice trouble', $ticket->subject);
        $this->assertSame('email', $ticket->channel->value);
    }

    public function test_the_message_appears_on_the_ticket(): void
    {
        $this->deliver($this->mail())->assertOk();

        $message = DB::table('ticket_messages')->first();

        $this->assertSame('inbound', $message->direction);
        $this->assertSame('My invoice is wrong.', $message->body);
    }

    public function test_an_unknown_sender_becomes_a_customer(): void
    {
        $this->deliver($this->mail())->assertOk();

        $customer = DB::table('customers')->first();

        /*
         * Created rather than refused. A support desk that only accepts email
         * from people already in the database is a support desk that cannot be
         * emailed.
         */
        $this->assertSame('Hana Yousef', $customer->full_name);
        $this->assertTrue((bool) $customer->auto_created);
        $this->assertSame('inbound_email', $customer->created_via);
    }

    public function test_an_auto_created_customer_is_otherwise_first_class(): void
    {
        $this->deliver($this->mail())->assertOk();

        $customer = DB::table('customers')->first();

        // A reference in the ordinary format, an active state, a searchable
        // identifier. The flag records that the record is thin, not that the
        // person is second-class.
        $this->assertMatchesRegularExpression('/^C-[0-9A-Z]{8}$/', $customer->reference);
        $this->assertSame('active', $customer->state);
        $this->assertSame(1, DB::table('contact_identifiers')->where('customer_id', $customer->id)->count());
    }

    public function test_a_known_sender_is_matched_not_duplicated(): void
    {
        $existing = $this->makeCustomer();

        DB::table('contact_identifiers')->insert([
            'id' => (string) Str::ulid(),
            'customer_id' => $existing,
            'kind' => 'email',
            'value' => 'Hana@Example.test',
            'value_normalised' => 'hana@example.test',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('customers')->count();

        $this->deliver($this->mail())->assertOk();

        /*
         * Matched case-insensitively. Without that, the first time somebody
         * capitalised their own address they would become a second customer
         * and their history would split in half.
         */
        $this->assertSame($before, DB::table('customers')->count());
        $this->assertSame($existing, DB::table('tickets')->value('customer_id'));
    }

    public function test_a_duplicate_delivery_changes_nothing(): void
    {
        $raw = $this->mail();

        $this->deliver($raw)->assertOk()->assertJsonPath('status', 'accepted');
        $this->deliver($raw)->assertOk()->assertJsonPath('status', 'duplicate');

        /*
         * Providers retry — that is the contract, not a fault. Without this a
         * customer who wrote once would find five identical requests with five
         * different references.
         */
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, DB::table('ticket_messages')->count());
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_the_providers_own_id_wins_over_the_message_id(): void
    {
        // Two genuinely different messages the provider says are one delivery.
        $this->deliver($this->mail(), ['message_id' => 'provider-abc'])->assertOk();
        $this->deliver($this->mail(body: 'Different words'), ['message_id' => 'provider-abc'])
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_a_message_with_no_message_id_is_still_deduplicated(): void
    {
        $raw = "From: hana@example.test\r\nSubject: No id\r\n\r\nBody";

        $this->deliver($raw)->assertOk();
        $this->deliver($raw)->assertOk()->assertJsonPath('status', 'duplicate');

        // Two identical deliveries of a malformed message are still one email.
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_the_history_records_the_system_as_the_actor(): void
    {
        $this->deliver($this->mail())->assertOk();

        $event = DB::table('ticket_events')->orderBy('created_at')->first();

        /*
         * The customer wrote the words, but no PERSON in this system performed
         * the action. Naming an agent would put a colleague against something
         * they did not do; naming the customer would imply a portal account
         * they do not have.
         */
        $this->assertSame('system', $event->actor_type);
        $this->assertNull($event->actor_id);
        $this->assertSame('inbound_email', $event->actor_reason);
    }

    public function test_an_arabic_message_survives_intact(): void
    {
        $arabic = 'الفاتورة فيها رسوم مكرّرة';

        $raw = "From: hana@example.test\r\nSubject: =?UTF-8?B?".base64_encode('فاتورة')."?=\r\n"
            ."MIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n".$arabic;

        $this->deliver($raw)->assertOk();

        // Encoded words in the subject and UTF-8 in the body — the two places a
        // hand-rolled parser silently mangles Arabic.
        $this->assertSame($arabic, DB::table('ticket_messages')->value('body'));
        $this->assertSame('فاتورة', Ticket::query()->value('subject'));
    }
}
