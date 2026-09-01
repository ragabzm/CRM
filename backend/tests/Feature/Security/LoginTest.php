<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Security\Events\StaffAuthAttempted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create([
            'email' => 'agent@ragab.test',
            'password' => Hash::make(self::PASSWORD),
            ...$attributes,
        ]);
    }

    public function test_a_staff_member_signs_in_with_email_and_password(): void
    {
        $user = $this->staff();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertOk();
        $response->assertJson([
            'id' => $user->id,
            'email' => $user->email,
            'preferred_locale' => 'en',
            'roles' => [],
        ]);

        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_the_session_survives_into_the_next_request(): void
    {
        $user = $this->staff();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        // The cookie the first response set is what authenticates this one.
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_no_token_is_issued_anywhere_in_the_response(): void
    {
        $user = $this->staff();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        // Cookie mode means no bearer credential the browser's JavaScript could
        // read, store, or leak.
        $body = (string) $response->getContent();
        foreach (['token', 'access_token', 'bearer', 'Bearer'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }

    public function test_the_session_id_is_regenerated_on_sign_in(): void
    {
        $user = $this->staff();

        $this->get('/api/v1/auth/session');
        $before = session()->getId();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        // A session id captured before sign-in must be worthless afterwards.
        $this->assertNotSame($before, session()->getId());
    }

    public function test_signing_in_records_a_success_in_the_audit_trail(): void
    {
        Event::fake([StaffAuthAttempted::class]);
        $user = $this->staff();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        Event::assertDispatched(
            StaffAuthAttempted::class,
            fn (StaffAuthAttempted $event) => $event->outcome === StaffAuthAttempted::OUTCOME_SUCCESS
                && $event->email === $user->email,
        );
    }

    public function test_signing_out_ends_the_session(): void
    {
        $user = $this->staff();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertFalse(Auth::guard('web')->check());
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_requires_a_session(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'security.session_expired');
    }

    public function test_the_session_endpoint_reports_the_configured_inactivity_window(): void
    {
        config()->set('auth.staff.inactivity_minutes', 45);

        $this->getJson('/api/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('inactivity_minutes', 45)
            ->assertJsonPath('authenticated', false);
    }

    public function test_login_needs_no_idempotency_key(): void
    {
        $user = $this->staff();

        // Session operations are exempt: a replayed sign-in cannot carry a
        // Set-Cookie, and requiring a key would break every ordinary form post.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();
    }
}
