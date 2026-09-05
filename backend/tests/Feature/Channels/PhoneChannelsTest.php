<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Modules\Channels\Adapters\PhoneChannelAdapter;
use App\Modules\Channels\Infrastructure\NullChannelTransport;
use App\Modules\Channels\Jobs\SendChannelMessageJob;
use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * WhatsApp and SMS, which are the same code twice.
 *
 * Most of these tests are written to run for BOTH channels from one data
 * provider, and that is the point rather than a convenience: the story's claim
 * is "one implementation with two configurations", and a test that only
 * covered WhatsApp would let SMS drift without anybody noticing.
 *
 * The test that earns its place last — `test_the_same_payload_through_two
 * _providers_produces_the_same_ticket` — is the one the AC asks for by name:
 * replacing a provider must change no channel behaviour, no ticket behaviour
 * and no history.
 */
final class PhoneChannelsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'a-shared-webhook-secret';

    private int $departmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->departmentId = (int) DB::table('departments')->insertGetId([
            'name' => 'Support',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([PhoneChannelAdapter::WHATSAPP, PhoneChannelAdapter::SMS] as $channel) {
            $this->account($channel);
        }
    }

    private function account(string $channel, string $provider = 'null', bool $active = true): string
    {
        $id = (string) Str::ulid();

        DB::table('channel_accounts')->insert([
            'id' => $id,
            'channel' => $channel,
            'name' => ucfirst($channel).' '.$provider,
            'is_active' => $active,
            'department_id' => $this->departmentId,
            'provider_config' => json_encode([
                'provider' => $provider,
                'number' => '+20 100 555 0900',
                'webhook_secret' => self::SECRET,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function inbound(string $channel, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Channel-Signature' => self::SECRET])
            ->postJson("/api/v1/inbound/{$channel}", array_merge([
                'from' => '+20 100 555 0101',
                'to' => '+20 100 555 0900',
                'body' => 'My invoice is wrong.',
                'message_id' => 'provider-'.Str::ulid(),
                'profile_name' => 'Hana Yousef',
            ], $overrides));
    }

    /** @return array<string, array{string}> */
    public static function channels(): array
    {
        return [
            'whatsapp' => [PhoneChannelAdapter::WHATSAPP],
            'sms' => [PhoneChannelAdapter::SMS],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_an_inbound_message_becomes_a_ticket(string $channel): void
    {
        $this->inbound($channel)->assertOk();

        $ticket = Ticket::query()->sole();

        $this->assertSame($channel, $ticket->channel->value);
        $this->assertSame($this->departmentId, (int) $ticket->department_id);

        // Through the spine, so it has the history the spine gives everything.
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->getKey(),
            'event_type' => 'ticket.created',
            'actor_reason' => 'inbound_'.$channel,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_the_sender_is_identified_by_the_right_kind_of_number(string $channel): void
    {
        $this->inbound($channel)->assertOk();

        $identifier = DB::table('contact_identifiers')->sole();

        /*
         * A WhatsApp number is its own kind. The same digits may also be on
         * record as a phone number — one person, one record — and recording
         * them separately is what lets a reply go back the way it came.
         */
        $expected = $channel === PhoneChannelAdapter::WHATSAPP
            ? ContactKind::WhatsApp->value
            : ContactKind::Phone->value;

        $this->assertSame($expected, $identifier->kind);
        $this->assertSame('1005550101', $identifier->value_normalised);
    }

    public function test_the_same_person_on_both_channels_is_one_customer(): void
    {
        $this->inbound(PhoneChannelAdapter::WHATSAPP)->assertOk();
        $this->inbound(PhoneChannelAdapter::SMS)->assertOk();

        /*
         * Two identifiers, one customer. The digits are identical and that is
         * not a duplicate — it is somebody who texts and messages from the
         * same handset, and treating them as two people splits their history
         * in half.
         */
        $this->assertSame(1, DB::table('customers')->count());
        $this->assertSame(2, DB::table('contact_identifiers')->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_the_same_provider_message_arriving_twice_makes_one_ticket(string $channel): void
    {
        $id = 'provider-fixed-id';

        $this->inbound($channel, ['message_id' => $id])->assertOk();
        $second = $this->inbound($channel, ['message_id' => $id]);

        $second->assertOk();
        $this->assertSame('duplicate', $second->json('status'));
        $this->assertSame(1, Ticket::query()->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_an_unsigned_request_is_refused_before_the_payload_is_read(string $channel): void
    {
        $this->postJson("/api/v1/inbound/{$channel}", ['from' => '+20100', 'body' => 'x'])
            ->assertStatus(401);

        $this->withHeaders(['X-Channel-Signature' => 'wrong'])
            ->postJson("/api/v1/inbound/{$channel}", ['from' => '+20100', 'body' => 'x'])
            ->assertStatus(401);

        $this->assertSame(0, Ticket::query()->count());
        // Nothing was written at all — not even a quarantine row.
        $this->assertSame(0, DB::table('inbound_messages')->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_a_disabled_channels_secret_does_not_open_the_door(string $channel): void
    {
        DB::table('channel_accounts')->where('channel', $channel)->update(['is_active' => false]);

        // Stopping inbound is the whole point of the switch.
        $this->inbound($channel)->assertStatus(401);
        $this->assertSame(0, Ticket::query()->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_a_message_with_no_sender_is_quarantined_rather_than_discarded(string $channel): void
    {
        $this->inbound($channel, ['from' => ''])->assertOk();

        $this->assertSame(1, DB::table('channel_quarantine')->where('channel', $channel)->count());
        $this->assertSame(0, Ticket::query()->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_a_reply_is_queued_on_the_channel_it_arrived_on(string $channel): void
    {
        Queue::fake();

        $this->inbound($channel)->assertOk();

        $ticket = Ticket::query()->sole();

        app(AppendMessage::class)->handle(
            Actor::staff('1', 'Dana Faris'),
            (string) $ticket->getKey(),
            MessageDirection::Outbound,
            'We are looking into it.',
        );

        Queue::assertPushed(SendChannelMessageJob::class);

        $message = DB::table('ticket_messages')->where('direction', 'outbound')->sole();
        $this->assertSame('queued', $message->delivery_state);
    }

    public function test_an_email_reply_is_not_also_sent_for_a_phone_ticket(): void
    {
        Queue::fake();

        $this->inbound(PhoneChannelAdapter::WHATSAPP)->assertOk();

        app(AppendMessage::class)->handle(
            Actor::staff('1', 'Dana Faris'),
            (string) Ticket::query()->sole()->getKey(),
            MessageDirection::Outbound,
            'We are looking into it.',
        );

        /*
         * Before this story the mail dispatcher took EVERY reply, so a
         * WhatsApp answer would have gone out twice: once on WhatsApp and once
         * as an email the customer never asked for, to an address they may
         * never have given us.
         */
        Queue::assertNotPushed(\App\Modules\Email\Jobs\SendOutboundEmailJob::class);
    }

    public function test_a_failed_send_stays_visible_with_something_to_do_about_it(): void
    {
        $this->inbound(PhoneChannelAdapter::WHATSAPP)->assertOk();

        $ticket = Ticket::query()->sole();

        // No number on record for the channel: nothing can be delivered.
        DB::table('contact_identifiers')->delete();

        app(AppendMessage::class)->handle(
            Actor::staff('1', 'Dana Faris'),
            (string) $ticket->getKey(),
            MessageDirection::Outbound,
            'We are looking into it.',
        );

        /*
         * UX-09. A reply nobody could deliver must be visible in the timeline
         * with Retry — the alternative is an agent believing they answered.
         */
        $this->assertSame(
            'failed',
            DB::table('ticket_messages')->where('direction', 'outbound')->value('delivery_state'),
        );
    }

    public function test_a_delivery_receipt_records_what_the_provider_reported(): void
    {
        $this->inbound(PhoneChannelAdapter::WHATSAPP)->assertOk();

        $messageId = (string) Str::ulid();
        DB::table('ticket_messages')->where('direction', 'inbound')->update([
            'provider_message_id' => $messageId,
            'delivery_state' => 'sent',
        ]);

        $this->withHeaders(['X-Channel-Signature' => self::SECRET])
            ->postJson('/api/v1/inbound/whatsapp/receipts', [
                'provider_message_id' => $messageId,
                'state' => 'delivered',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'recorded');

        $this->assertSame(
            'delivered',
            DB::table('ticket_messages')->where('provider_message_id', $messageId)->value('delivery_state'),
        );
    }

    public function test_a_receipt_cannot_report_a_state_it_has_no_business_reporting(): void
    {
        // `queued` describes OUR side of the exchange, not the provider's;
        // letting a receipt write it would let one move a message backwards.
        $this->withHeaders(['X-Channel-Signature' => self::SECRET])
            ->postJson('/api/v1/inbound/whatsapp/receipts', [
                'provider_message_id' => 'anything',
                'state' => 'queued',
            ])
            ->assertStatus(422);
    }

    public function test_the_same_payload_through_two_providers_produces_the_same_ticket(): void
    {
        /*
         * The AC by name: replacing a provider changes no channel behaviour,
         * no ticket behaviour and no history.
         *
         * Same payload, two accounts differing only in their provider name.
         * Everything that comes out is compared field by field.
         */
        $payload = [
            'from' => '+20 100 555 0102',
            'to' => '+20 100 555 0900',
            'body' => 'The same words both times.',
            'profile_name' => 'Omar Farouk',
        ];

        $this->withHeaders(['X-Channel-Signature' => self::SECRET])
            ->postJson('/api/v1/inbound/sms', $payload + ['message_id' => 'first-provider'])
            ->assertOk();

        $first = Ticket::query()->sole();
        $firstEvents = DB::table('ticket_events')->where('ticket_id', $first->getKey())
            ->orderBy('created_at')->pluck('event_type')->all();
        $firstCorrelation = DB::table('inbound_messages')->value('correlation_reason');

        // A second deployment, a different provider, everything else equal.
        DB::table('inbound_messages')->delete();
        DB::table('ticket_events')->delete();
        DB::table('ticket_messages')->delete();
        DB::table('tickets')->delete();
        DB::table('channel_accounts')->where('channel', 'sms')->delete();
        $this->account('sms', provider: 'some-other-gateway');

        $this->withHeaders(['X-Channel-Signature' => self::SECRET])
            ->postJson('/api/v1/inbound/sms', $payload + ['message_id' => 'second-provider'])
            ->assertOk();

        $second = Ticket::query()->sole();

        $this->assertSame($first->subject, $second->subject);
        $this->assertSame($first->channel->value, $second->channel->value);
        $this->assertSame($first->department_id, $second->department_id);
        $this->assertSame($first->status->value, $second->status->value);

        $this->assertSame(
            $firstEvents,
            DB::table('ticket_events')->where('ticket_id', $second->getKey())
                ->orderBy('created_at')->pluck('event_type')->all(),
        );

        $this->assertSame($firstCorrelation, DB::table('inbound_messages')->value('correlation_reason'));
    }

    public function test_an_unreachable_provider_does_not_stop_a_ticket_being_worked(): void
    {
        $this->inbound(PhoneChannelAdapter::WHATSAPP)->assertOk();

        $ticket = Ticket::query()->sole();

        /*
         * A transport that always throws, standing in for a dead gateway. An
         * implementation of the port rather than a subclass of the null one —
         * that class is `final` on purpose, so a test double has to satisfy
         * the same contract a real provider would.
         */
        $this->app->bind(
            NullChannelTransport::class,
            fn () => new class implements \App\Modules\Channels\Contracts\ChannelTransport
            {
                public function provider(): string
                {
                    return 'null';
                }

                public function send(string $to, string $body, array $configuration): string
                {
                    throw \App\Modules\Channels\Contracts\ChannelTransportFailure::temporary('Gateway down.');
                }

                public function supportsInbound(): bool
                {
                    return true;
                }
            },
        );

        app(AppendMessage::class)->handle(
            Actor::staff('1', 'Dana Faris'),
            (string) $ticket->getKey(),
            MessageDirection::Outbound,
            'We are looking into it.',
        );

        /*
         * The reply is recorded, the ticket is untouched, and the interface is
         * never blocked. Delivering it is a separate promise.
         */
        $this->assertSame(2, DB::table('ticket_messages')->count());
        $this->assertSame('open', $ticket->refresh()->status->value);
    }
}
