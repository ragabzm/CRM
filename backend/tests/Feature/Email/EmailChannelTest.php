<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Contracts\MailTransportFailure;
use App\Modules\Email\Domain\MailLogEntry;
use App\Modules\Email\Infrastructure\NullMailTransport;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Configuring the channel, proving it works, and keeping the log honest.
 */
final class EmailChannelTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->settings()->set('email.enabled', true, null);
        $this->settings()->set('email.acknowledgement.enabled', false, null);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    private function admin(): \App\Models\User
    {
        return $this->makeUser(Roles::ADMINISTRATOR);
    }

    private function testSend(array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/admin/email/test', $body);
    }

    public function test_an_administrator_can_prove_the_channel_works(): void
    {
        /*
         * A real send is the only honest answer. A green tick because the
         * fields are filled in tells an administrator the channel is fine right
         * up until the first customer does not get a reply.
         */
        $this->testSend(['to' => 'admin@example.test'])
            ->assertOk()
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('sent_to', 'admin@example.test');
    }

    public function test_the_test_send_defaults_to_the_administrators_own_address(): void
    {
        $admin = $this->admin();

        // A test that emails somebody else is a test whose result they cannot
        // see.
        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/admin/email/test')
            ->assertOk()
            ->assertJsonPath('sent_to', $admin->email);
    }

    public function test_a_failed_test_send_says_what_the_provider_said(): void
    {
        $this->app->instance(MailTransport::class, new class implements MailTransport
        {
            public function send(string $a, string $b, string $c, string $d, array $e, string $f): void
            {
                throw MailTransportFailure::permanent('535 Authentication credentials invalid', '535');
            }

            public function name(): string
            {
                return 'smtp';
            }
        });

        $response = $this->testSend(['to' => 'admin@example.test'])->assertStatus(502);

        /*
         * The difference between a five-minute fix and a support ticket. An
         * administrator cannot tell an expired API key from a blocked port
         * from a rejected sender domain without this.
         */
        $response->assertJsonPath('code', 'email.test_send_failed');
        $this->assertStringContainsString('535', (string) $response->json('detail'));
        $this->assertFalse($response->json('retryable'));
    }

    public function test_a_test_send_to_a_malformed_address_is_refused_before_it_is_tried(): void
    {
        $this->testSend(['to' => 'not-an-address'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'email.invalid_recipient');
    }

    public function test_an_agent_cannot_send_a_test(): void
    {
        // The console is an administrator's surface; the log below names every
        // address the system has written to.
        $this->actingAs($this->makeUser(Roles::AGENT))
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/admin/email/test')
            ->assertStatus(403);
    }

    public function test_the_provider_is_chosen_by_a_setting(): void
    {
        // Swapping providers is a stored value, not a code change.
        $this->assertInstanceOf(NullMailTransport::class, $this->app->make(MailTransport::class));

        $this->settings()->set('email.provider', 'log', null);
        $this->app->forgetInstance(MailTransport::class);

        $this->assertSame('log', $this->app->make(MailTransport::class)->name());
    }

    public function test_the_null_transport_is_the_default(): void
    {
        /*
         * Which is what makes this suite runnable on a laptop and in a fresh
         * container. A test suite that quietly required a reachable SMTP server
         * is a suite whose email tests everybody skips.
         */
        $this->assertSame('null', $this->settings()->get('email.provider'));
    }

    public function test_an_administrator_can_read_what_the_channel_has_done(): void
    {
        $this->testSend(['to' => 'admin@example.test'])->assertOk();

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/email/log')
            ->assertOk();

        $this->assertGreaterThan(0, $response->json('meta.total'));
        $this->assertSame('sent', $response->json('data.0.status'));
    }

    public function test_the_log_can_be_narrowed_to_what_did_not_go_out(): void
    {
        $this->testSend(['to' => 'admin@example.test'])->assertOk();

        // The filter that matters when somebody asks "did that reply leave?"
        $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/email/log?status=failed')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_an_agent_cannot_read_the_mail_log(): void
    {
        // It names every address the system has written to — not something an
        // agent needs, and exactly what an attacker with an agent account wants.
        $this->actingAs($this->makeUser(Roles::AGENT))
            ->getJson('/api/v1/admin/email/log')
            ->assertStatus(403);
    }

    public function test_old_log_entries_are_pruned(): void
    {
        $this->settings()->set('email.log.retention_days', 30, null);

        $this->seedLogEntry(occurredAt: now()->subDays(40));
        $this->seedLogEntry(occurredAt: now()->subDays(5));

        Artisan::call('email:prune-log');

        // A busy desk writes thousands of these a day, and their value falls
        // off a cliff once the incident they belonged to is closed.
        $this->assertSame(1, MailLogEntry::query()->count());
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $this->settings()->set('email.log.retention_days', 1, null);
        $this->seedLogEntry(occurredAt: now()->subDays(40));

        Artisan::call('email:prune-log', ['--dry-run' => true]);

        $this->assertSame(1, MailLogEntry::query()->count());
    }

    public function test_a_retention_of_nothing_cannot_be_configured(): void
    {
        /*
         * Zero days would mean deleting the log as fast as it is written. The
         * setting's own rule refuses it, which is why the command's matching
         * guard is unreachable through the console — it stays as a second
         * barrier for a value written straight into the table.
         */
        $this->actingAs($this->admin())
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->patchJson('/api/v1/admin/settings/email.log.retention_days', ['value' => 0])
            ->assertStatus(422);

        $this->assertSame(90, $this->settings()->get('email.log.retention_days'));
    }

    private function seedLogEntry(\Illuminate\Support\Carbon $occurredAt): void
    {
        DB::table('mail_log')->insert([
            'id' => (string) Str::ulid(),
            'direction' => 'outbound',
            'provider' => 'null',
            'address' => 'someone@example.test',
            'status' => 'sent',
            'attempt' => 1,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }
}
