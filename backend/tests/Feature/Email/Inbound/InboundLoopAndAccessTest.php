<?php

declare(strict_types=1);

namespace Tests\Feature\Email\Inbound;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Infrastructure\NullMailTransport;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Two things that must never happen: a mail loop, and an open door.
 */
final class InboundLoopAndAccessTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private const SECRET = 'a-shared-secret';

    private NullMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $s = $this->settings();
        $s->set('email.inbound.enabled', true, null);
        $s->set('email.inbound.webhook_secret', self::SECRET, null);
        $s->set('email.enabled', true, null);
        $s->set('email.acknowledgement.enabled', true, null);
        $s->set('email.domain', 'support.example.test', null);

        $this->transport = $this->app->make(NullMailTransport::class);
        $this->app->instance(MailTransport::class, $this->transport);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    private function mail(array $extraHeaders = []): string
    {
        return implode("\r\n", array_merge([
            'From: Hana <hana@example.test>',
            'To: support@example.test',
            'Subject: Out of office',
            'Message-ID: <'.Str::ulid().'@mail.example.test>',
        ], $extraHeaders))."\r\n\r\nI am away until Monday.";
    }

    private function deliver(string $raw, ?string $secret = self::SECRET): \Illuminate\Testing\TestResponse
    {
        $request = $secret === null ? $this : $this->withHeader('X-Webhook-Secret', $secret);

        return $request->postJson('/api/v1/inbound/email', ['raw' => $raw]);
    }

    public function test_an_ordinary_email_is_acknowledged(): void
    {
        $this->deliver($this->mail())->assertOk();

        // The baseline the loop tests are measured against.
        $this->assertNotNull($this->transport->lastSent());
    }

    public function test_an_auto_submitted_message_is_never_acknowledged(): void
    {
        $this->deliver($this->mail(['Auto-Submitted: auto-replied']))->assertOk();

        /*
         * Replying to an auto-reply produces an auto-reply, and two systems
         * will do that to each other until somebody notices the mailbox.
         */
        $this->assertNull($this->transport->lastSent());
        $this->assertSame(1, Ticket::query()->count(), 'The ticket is still created.');
    }

    public function test_a_mailing_list_message_is_never_acknowledged(): void
    {
        $this->deliver($this->mail(['List-Id: <announce.example.test>']))->assertOk();

        $this->assertNull($this->transport->lastSent());
    }

    public function test_bulk_precedence_is_never_acknowledged(): void
    {
        $this->deliver($this->mail(['Precedence: bulk']))->assertOk();

        $this->assertNull($this->transport->lastSent());
    }

    public function test_auto_submitted_no_means_a_person_sent_it(): void
    {
        // The one value of that header that means "a human", per RFC 3834.
        $this->deliver($this->mail(['Auto-Submitted: no']))->assertOk();

        $this->assertNotNull($this->transport->lastSent());
    }

    public function test_the_loop_decision_is_recorded(): void
    {
        $this->deliver($this->mail(['Auto-Submitted: auto-replied']))->assertOk();

        $trace = json_decode((string) DB::table('mail_inbound')->value('correlation_trace'), true);

        // So a decision not to reply is auditable after the fact, rather than
        // looking like a bug in the acknowledgement.
        $this->assertTrue($trace['automated']);
    }

    public function test_a_caller_with_no_secret_is_refused(): void
    {
        $this->deliver($this->mail(), secret: null)->assertStatus(401);

        // The one unauthenticated write in the system: anybody who found this
        // URL could otherwise open tickets in any customer's name.
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_a_caller_with_the_wrong_secret_is_refused(): void
    {
        $this->deliver($this->mail(), secret: 'not-the-secret')->assertStatus(401);

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_the_endpoint_is_invisible_while_inbound_is_off(): void
    {
        $this->settings()->set('email.inbound.enabled', false, null);

        /*
         * 404, not 403. A disabled endpoint should be indistinguishable from
         * one that was never built: telling an unauthenticated caller "this
         * exists but is switched off" is telling them to come back later.
         */
        $this->deliver($this->mail())->assertStatus(404);
    }

    public function test_it_refuses_to_run_before_a_secret_is_set(): void
    {
        $this->settings()->set('email.inbound.webhook_secret', '', null);

        // Accepting anonymous mail into the ticket system while nobody has
        // configured the guard is worse than not accepting mail.
        $this->deliver($this->mail(), secret: 'anything')->assertStatus(503);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_it_needs_no_idempotency_key(): void
    {
        // Providers do not send one. Idempotency here is enforced on the
        // message's own id, which is stronger: it survives a retry from a
        // different provider process.
        $this->deliver($this->mail())->assertOk();
    }
}
