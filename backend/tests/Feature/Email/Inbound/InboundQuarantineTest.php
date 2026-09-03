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
 * The mail nobody could turn into anything.
 *
 * A customer who emails support and hears nothing back has been ignored,
 * whatever the technical reason. Silently dropping an unparseable message means
 * nobody ever learns it happened — not the customer, who waits, and not the
 * administrator, who has no reason to look.
 */
final class InboundQuarantineTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private const SECRET = 'a-shared-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $s = $this->app->make(SettingsRegistry::class);
        $s->set('email.inbound.enabled', true, null);
        $s->set('email.inbound.webhook_secret', self::SECRET, null);
        $s->set('email.enabled', true, null);
        $s->set('email.acknowledgement.enabled', false, null);

        $this->app->instance(MailTransport::class, $this->app->make(NullMailTransport::class));
    }

    private function deliver(string $raw): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Webhook-Secret', self::SECRET)
            ->postJson('/api/v1/inbound/email', ['raw' => $raw]);
    }

    /** No From header: everything downstream hangs off it. */
    private const UNPARSEABLE = "To: support@example.test\r\nSubject: Broken\r\n\r\nBody with no sender";

    public function test_an_unreadable_message_is_kept_not_dropped(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk()->assertJsonPath('status', 'quarantined');

        $this->assertSame(1, DB::table('mail_quarantine')->count());
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_the_raw_bytes_are_kept_in_full(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk();

        // A parser bug is only diagnosable against the bytes that broke it, and
        // replaying once it is fixed is why this is kept.
        $this->assertSame(self::UNPARSEABLE, DB::table('mail_quarantine')->value('raw'));
    }

    public function test_it_records_the_parsers_own_words(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk();

        $reason = (string) DB::table('mail_quarantine')->value('reason');

        // "Could not process" would leave an administrator with nothing to act
        // on.
        $this->assertStringContainsString('From', $reason);
    }

    public function test_an_empty_delivery_is_refused_before_quarantine(): void
    {
        // Nothing to keep and nothing to diagnose — this is a broken caller,
        // not a broken message.
        $this->withHeader('X-Webhook-Secret', self::SECRET)
            ->postJson('/api/v1/inbound/email', ['raw' => ''])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('mail_quarantine')->count());
    }

    public function test_a_retried_unparseable_message_is_quarantined_once(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk();
        $this->deliver(self::UNPARSEABLE)->assertOk()->assertJsonPath('status', 'duplicate');

        // Otherwise a provider retrying for an hour fills quarantine with
        // copies of one message.
        $this->assertSame(1, DB::table('mail_quarantine')->count());
    }

    public function test_an_administrator_can_see_what_was_quarantined(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk();

        $response = $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->getJson('/api/v1/admin/email/quarantine')
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Broken', $response->json('data.0.subject'));
    }

    public function test_the_list_does_not_carry_the_raw_source(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk();

        $response = $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->getJson('/api/v1/admin/email/quarantine')
            ->assertOk();

        /*
         * It is a customer's entire email — their words, their address, their
         * attachments — and a list view has no reason to carry twenty of them.
         * It is fetched deliberately, one at a time, by somebody diagnosing.
         */
        $this->assertArrayNotHasKey('raw', $response->json('data.0'));
        $this->assertGreaterThan(0, $response->json('data.0.raw_bytes'));
    }

    public function test_a_single_message_can_be_read_in_full(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk();
        $id = DB::table('mail_quarantine')->value('id');

        $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->getJson("/api/v1/admin/email/quarantine/{$id}")
            ->assertOk()
            ->assertJsonPath('raw', self::UNPARSEABLE);
    }

    public function test_an_agent_cannot_read_quarantined_mail(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk();

        /*
         * It is the raw source of a customer's email, for mail that failed
         * before any of the usual access rules could apply to it.
         */
        $this->actingAs($this->makeUser(Roles::AGENT))
            ->getJson('/api/v1/admin/email/quarantine')
            ->assertStatus(403);
    }

    public function test_a_fixed_message_can_be_replayed(): void
    {
        $this->deliver("To: support@example.test\r\nSubject: Broken\r\n\r\nNo sender")->assertOk();

        $id = DB::table('mail_quarantine')->value('id');

        // Repaired by hand, as an administrator would after finding the fault.
        DB::table('mail_quarantine')->where('id', $id)->update([
            'raw' => "From: hana@example.test\r\nTo: support@example.test\r\nSubject: Fixed\r\n\r\nNow it parses",
        ]);

        $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson("/api/v1/admin/email/quarantine/{$id}/replay")
            ->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_a_replay_marks_the_message_handled(): void
    {
        $this->deliver(self::UNPARSEABLE)->assertOk();
        $id = DB::table('mail_quarantine')->value('id');

        $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson("/api/v1/admin/email/quarantine/{$id}/replay")
            ->assertOk();

        $this->assertNotNull(DB::table('mail_quarantine')->where('id', $id)->value('resolved_at'));
    }

    public function test_a_message_cannot_be_replayed_twice(): void
    {
        $this->deliver("From: hana@example.test\r\nSubject: x\r\n\r\nBody")->assertOk();

        // Force something into quarantine that will parse on replay.
        DB::table('mail_quarantine')->insert([
            'id' => (string) Str::ulid(),
            'provider' => 'generic',
            'reason' => 'test',
            'raw' => "From: hana@example.test\r\nSubject: Replayable\r\n\r\nBody",
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('mail_quarantine')->where('reason', 'test')->value('id');
        $admin = $this->makeUser(Roles::ADMINISTRATOR);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson("/api/v1/admin/email/quarantine/{$id}/replay")
            ->assertOk();

        // A second replay would create a second ticket for one email.
        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson("/api/v1/admin/email/quarantine/{$id}/replay")
            ->assertStatus(409);
    }
}
