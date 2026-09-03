<?php

declare(strict_types=1);

namespace Tests\Feature\Email\Inbound;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Infrastructure\NullMailTransport;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Which ticket a reply belongs to, and how we decided.
 *
 * Four rules in descending order of how much the sender's mail client had to
 * get right. Guessing from the sender's address alone is deliberately not one
 * of them: a customer with three open tickets would have their reply attached
 * to whichever happened to be newest, and the mistake would be invisible to
 * everyone including them.
 */
final class InboundCorrelationTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private const SECRET = 'a-shared-secret';

    private Ticket $ticket;

    private string $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $s = $this->app->make(SettingsRegistry::class);
        $s->set('email.inbound.enabled', true, null);
        $s->set('email.inbound.webhook_secret', self::SECRET, null);
        $s->set('email.enabled', true, null);
        $s->set('email.domain', 'support.example.test', null);
        $s->set('email.acknowledgement.enabled', false, null);

        $this->app->instance(MailTransport::class, $this->app->make(NullMailTransport::class));

        $this->customerId = $this->makeCustomer();

        DB::table('contact_identifiers')->insert([
            'id' => (string) Str::ulid(),
            'customer_id' => $this->customerId,
            'kind' => 'email',
            'value' => 'hana@example.test',
            'value_normalised' => 'hana@example.test',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->ticket = $this->makeTicket([
            'customer_id' => $this->customerId,
            'subject' => 'Invoice trouble',
            'status' => 'pending',
        ]);
    }

    /** Our own outbound message, as Story 5.1 would have left it. */
    private function ourReply(string $messageId): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $message = $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $this->ticket->getKey(),
            MessageDirection::Outbound,
            'Looking into it.',
        );

        DB::table('ticket_messages')->where('id', $message->getKey())
            ->update(['email_message_id' => $messageId]);
    }

    private function deliver(array $headers, string $body = 'Any news?'): \Illuminate\Testing\TestResponse
    {
        $raw = implode("\r\n", array_merge([
            'From: hana@example.test',
            'To: support@example.test',
            'Message-ID: <'.Str::ulid().'@mail.example.test>',
        ], $headers))."\r\n\r\n".$body;

        return $this->withHeader('X-Webhook-Secret', self::SECRET)
            ->postJson('/api/v1/inbound/email', ['raw' => $raw]);
    }

    private function trace(): array
    {
        return json_decode((string) DB::table('mail_inbound')->latest('received_at')->value('correlation_trace'), true);
    }

    public function test_in_reply_to_wins_when_the_client_threaded_properly(): void
    {
        $this->ourReply('<ours.1@support.example.test>');

        $this->deliver([
            'Subject: Re: Invoice trouble',
            'In-Reply-To: <ours.1@support.example.test>',
        ])->assertOk();

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('in_reply_to', $this->trace()['winning_rule']);
    }

    public function test_a_message_id_without_brackets_still_matches(): void
    {
        $this->ourReply('<ours.1@support.example.test>');

        // Clients echo the id both ways. Matching only one form loses the
        // correlation for half of them.
        $this->deliver([
            'Subject: Re: Invoice trouble',
            'In-Reply-To: ours.1@support.example.test',
        ])->assertOk();

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_references_catches_a_client_that_lost_the_parent(): void
    {
        $this->ourReply('<ours.1@support.example.test>');

        // Happens when a middle message was deleted or never arrived.
        $this->deliver([
            'Subject: Re: Invoice trouble',
            'References: <gone@x.test> <ours.1@support.example.test>',
        ])->assertOk();

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('references', $this->trace()['winning_rule']);
    }

    public function test_the_subject_tag_catches_a_client_that_stripped_the_headers(): void
    {
        $this->deliver(['Subject: Re: [#'.$this->ticket->reference.'] Invoice trouble'])->assertOk();

        /*
         * A webmail that rewrites the message, or a customer replying from a
         * brand-new email. Without this the reply becomes a second ticket about
         * a problem that already has one.
         */
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('subject_token', $this->trace()['winning_rule']);
    }

    public function test_a_reference_quoted_in_the_body_is_the_last_resort(): void
    {
        $this->deliver(
            ['Subject: A completely different subject'],
            "Hi — about [#{$this->ticket->reference}], any news?",
        )->assertOk();

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('body_token', $this->trace()['winning_rule']);
    }

    public function test_an_unrelated_email_opens_a_new_ticket(): void
    {
        $this->deliver(['Subject: Something else entirely'])->assertOk();

        // Guessing from the sender alone is NOT a rule: this customer already
        // has an open ticket, and attaching an unrelated message to it would be
        // invisible to everybody.
        $this->assertSame(2, Ticket::query()->count());
        $this->assertSame('new_ticket', $this->trace()['winning_rule']);
    }

    public function test_the_trace_records_what_the_losing_rules_saw(): void
    {
        $this->deliver(['Subject: Re: [#'.$this->ticket->reference.'] Invoice trouble'])->assertOk();

        $attempts = $this->trace()['attempts'];

        /*
         * "Why did this land on that ticket?" is the question every
         * mis-correlated email raises, and without the trace the only way to
         * answer is to re-run the logic against a message that has since been
         * consumed.
         */
        $this->assertSame(
            ['in_reply_to', 'references', 'subject_token'],
            array_column($attempts, 'rule'),
        );
        $this->assertSame([false, false, true], array_column($attempts, 'matched'));
    }

    public function test_a_reply_reopens_a_pending_ticket(): void
    {
        $this->assertSame('pending', $this->ticket->status->value);

        $this->deliver(['Subject: Re: [#'.$this->ticket->reference.'] Invoice trouble'])->assertOk();

        // A customer who has answered is waiting again, and a ticket left
        // Pending drops out of every "needs attention" count.
        $this->assertSame('open', $this->ticket->refresh()->status->value);
    }

    public function test_a_reply_lands_on_the_right_ticket_when_the_customer_has_several(): void
    {
        $other = $this->makeTicket(['customer_id' => $this->customerId, 'subject' => 'Another thing']);

        $this->deliver(['Subject: Re: [#'.$other->reference.'] Another thing'])->assertOk();

        $this->assertSame(
            1,
            DB::table('ticket_messages')->where('ticket_id', $other->getKey())->where('direction', 'inbound')->count(),
        );
        $this->assertSame(
            0,
            DB::table('ticket_messages')->where('ticket_id', $this->ticket->getKey())->where('direction', 'inbound')->count(),
        );
    }
}
