<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Tests\TestCase;

/**
 * "Passwords are never logged or returned by any endpoint."
 *
 * The assertions look for the PLAINTEXT and for the stored hash. Both matter: a
 * hash in a response body is not a catastrophe but it is a gift to an offline
 * cracker, and neither has any business leaving the server.
 */
final class PasswordNotLeakedTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
    }

    private function signedIn(): User
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        return $user;
    }

    private function assertNoCredentialIn(string $body, User $user): void
    {
        $this->assertStringNotContainsString(self::PASSWORD, $body);
        $this->assertStringNotContainsString($user->password, $body);
        $this->assertStringNotContainsString('remember_token', $body);
        $this->assertStringNotContainsString('"password"', $body);
    }

    public function test_the_login_response_carries_no_credential(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $this->assertNoCredentialIn((string) $response->getContent(), $user->refresh());
    }

    public function test_the_me_response_carries_no_credential(): void
    {
        $user = $this->signedIn();

        $response = $this->getJson('/api/v1/auth/me');

        $this->assertNoCredentialIn((string) $response->getContent(), $user);
    }

    public function test_the_profile_response_carries_no_credential(): void
    {
        $user = $this->signedIn();

        $response = $this->getJson('/api/v1/profile');

        $this->assertNoCredentialIn((string) $response->getContent(), $user);
    }

    public function test_a_failed_sign_in_does_not_echo_the_attempted_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'the-attackers-guess',
        ]);

        $this->assertStringNotContainsString('the-attackers-guess', (string) $response->getContent());
    }

    public function test_a_validation_failure_does_not_echo_the_password(): void
    {
        // Laravel's validation errors echo the field NAME, never its value —
        // this pins that, because an error body is the easiest place to leak one.
        $response = $this->postJson('/api/v1/profile/password', [
            'current_password' => 'secret-current-value',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $this->assertStringNotContainsString('secret-current-value', (string) $response->getContent());
    }

    public function test_the_audit_trail_records_no_credential(): void
    {
        $handler = new TestHandler(Level::Debug);
        Log::extend('audit-spy', fn () => new Logger('audit-spy', [$handler]));
        config()->set('logging.channels.audit-spy', ['driver' => 'audit-spy']);
        Log::setDefaultDriver('audit-spy');

        // Point the audit channel at the spy.
        config()->set('logging.channels.audit', ['driver' => 'audit-spy']);

        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'the-attackers-guess',
        ])->assertStatus(401);

        $records = json_encode(
            array_map(fn ($record) => [$record->message, $record->context], $handler->getRecords()),
            JSON_THROW_ON_ERROR,
        );

        $this->assertStringContainsString('staff.auth.attempted', $records);
        $this->assertStringNotContainsString(self::PASSWORD, $records);
        $this->assertStringNotContainsString('the-attackers-guess', $records);
        $this->assertStringNotContainsString($user->refresh()->password, $records);
    }
}
