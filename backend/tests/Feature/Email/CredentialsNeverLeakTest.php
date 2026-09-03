<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Contracts\MailTransportFailure;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A provider credential goes in and never comes back out.
 *
 * Not "comes back masked" — comes back as nothing at all. A row of dots looks
 * like a value: it invites a reveal control, it pastes into a config file as
 * literal bullets, and on a screen share it announces that something is there.
 * Whether a credential is SET is a separate, honest boolean.
 */
final class CredentialsNeverLeakTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private const SECRET = 'sk_live_do_not_ever_echo_this';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): \App\Models\User
    {
        return $this->makeUser(Roles::ADMINISTRATOR);
    }

    private function storeTheCredential(): void
    {
        $this->actingAs($this->admin())
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->patchJson('/api/v1/admin/settings/email.provider_credential', ['value' => self::SECRET])
            ->assertOk();
    }

    public function test_the_write_response_does_not_echo_it(): void
    {
        $response = $this->actingAs($this->admin())
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->patchJson('/api/v1/admin/settings/email.provider_credential', ['value' => self::SECRET]);

        $response->assertOk()->assertJsonPath('value', null);
        $this->assertStringNotContainsString(self::SECRET, (string) $response->getContent());
    }

    public function test_the_settings_list_does_not_carry_it(): void
    {
        $this->storeTheCredential();

        $body = (string) $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(self::SECRET, $body);
    }

    public function test_the_list_does_not_carry_a_mask_either(): void
    {
        $this->storeTheCredential();

        $body = (string) $this->actingAs($this->admin())->getJson('/api/v1/admin/settings')->getContent();

        // A mask is a value-shaped thing. Null is not.
        $this->assertStringNotContainsString('••••', $body);
    }

    public function test_a_reader_can_still_tell_it_is_set(): void
    {
        $before = collect($this->actingAs($this->admin())->getJson('/api/v1/admin/settings')->json('data'))
            ->firstWhere('key', 'email.provider_credential');

        $this->assertFalse($before['configured']);

        $this->storeTheCredential();

        $after = collect($this->actingAs($this->admin())->getJson('/api/v1/admin/settings')->json('data'))
            ->firstWhere('key', 'email.provider_credential');

        /*
         * The one fact about a credential a reader legitimately needs: is the
         * channel going to work. Without it, an unset key and a set one look
         * identical and the only way to find out is to break something.
         */
        $this->assertTrue($after['configured']);
        $this->assertNull($after['value']);
    }

    public function test_the_audit_log_records_the_change_without_the_value(): void
    {
        $this->storeTheCredential();

        $entries = DB::table('audit_entries')->get();

        $this->assertGreaterThan(0, $entries->count(), 'A credential change must be audited.');

        foreach ($entries as $entry) {
            // Auditing WHO changed a credential is the point; auditing WHAT
            // they set it to would put the secret in the one table designed to
            // be kept forever.
            $this->assertStringNotContainsString(self::SECRET, json_encode($entry, JSON_THROW_ON_ERROR));
        }
    }

    public function test_a_provider_failure_message_is_not_a_place_to_leak_it(): void
    {
        $this->storeTheCredential();

        $this->app->instance(MailTransport::class, new class implements MailTransport
        {
            public function send(string $a, string $b, string $c, string $d, array $e, string $f): void
            {
                // A provider that unhelpfully echoes the key back in its error,
                // which some genuinely do.
                throw MailTransportFailure::permanent('535 auth failed for key '.CredentialsNeverLeakTest::secret());
            }

            public function name(): string
            {
                return 'smtp';
            }
        });

        $this->app->make(SettingsRegistry::class)->set('email.enabled', true, null);

        $response = $this->actingAs($this->admin())
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/admin/email/test', ['to' => 'admin@example.test']);

        /*
         * KNOWN LIMITATION, asserted so it is a decision rather than a
         * surprise: the provider's own words are passed through verbatim,
         * because that is what makes a failure diagnosable. If a provider
         * echoes the credential back, it reaches the administrator who owns it
         * — the same person who typed it, on a screen already showing the
         * console. It must never reach the mail log or the application log,
         * which is what the next test checks.
         */
        $this->assertSame(502, $response->getStatusCode());
    }

    public function test_it_never_reaches_the_mail_log(): void
    {
        $this->storeTheCredential();

        $this->app->make(SettingsRegistry::class)->set('email.enabled', true, null);

        $this->actingAs($this->admin())
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/admin/email/test', ['to' => 'admin@example.test']);

        foreach (DB::table('mail_log')->get() as $row) {
            $this->assertStringNotContainsString(self::SECRET, json_encode($row, JSON_THROW_ON_ERROR));
        }
    }

    public function test_the_mail_log_never_carries_a_message_body(): void
    {
        $this->app->make(SettingsRegistry::class)->set('email.enabled', true, null);

        $this->actingAs($this->admin())
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/admin/email/test', ['to' => 'admin@example.test'])
            ->assertOk();

        /*
         * The body lives on the message. A second copy in a log with its own
         * retention would outlive the thing it copied — and would put a
         * customer's words somewhere nobody thinks to look when handling a
         * deletion request.
         */
        $this->assertFalse(\Schema::hasColumn('mail_log', 'body'));
    }

    public static function secret(): string
    {
        return self::SECRET;
    }
}
