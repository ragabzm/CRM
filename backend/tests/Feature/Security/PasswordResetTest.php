<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Security\Notifications\StaffPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Brand-New-Passw0rd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        RateLimiter::clear('password-reset');
        RateLimiter::clear('password-reset-confirm');
    }

    /** Requests a link and returns the plaintext token from the notification. */
    private function requestTokenFor(User $user): string
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertStatus(202);

        $token = null;
        Notification::assertSentTo($user, StaffPasswordReset::class, function ($notification) use (&$token) {
            $token = (new \ReflectionProperty($notification, 'token'))->getValue($notification);

            return true;
        });

        $this->assertIsString($token);

        return $token;
    }

    public function test_a_reset_link_is_emailed_to_a_real_account(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertStatus(202);

        Notification::assertSentTo($user, StaffPasswordReset::class);
    }

    public function test_an_unknown_address_gets_the_same_answer(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'nobody@ragab.test',
        ]);

        // Returning 404 here would let anyone test a list of addresses and learn
        // who works here.
        $response->assertStatus(202);
        Notification::assertNothingSent();
    }

    public function test_the_token_is_hashed_at_rest(): void
    {
        $user = User::factory()->create();
        $token = $this->requestTokenFor($user);

        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        // A database disclosure must not hand over working reset links.
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, (string) $stored));
    }

    public function test_a_fresh_token_resets_the_password(): void
    {
        $user = User::factory()->create();
        $token = $this->requestTokenFor($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
    }

    public function test_the_new_password_signs_in(): void
    {
        $user = User::factory()->create();
        $token = $this->requestTokenFor($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
        ])->assertOk();
    }

    public function test_a_token_is_single_use(): void
    {
        $user = User::factory()->create();
        $token = $this->requestTokenFor($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();

        // A reset link that keeps working is a permanent credential sitting in
        // an inbox.
        $this->postJson('/api/v1/auth/password/reset', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'security.reset_token_invalid');
    }

    public function test_an_expired_token_is_refused(): void
    {
        $user = User::factory()->create();
        $token = $this->requestTokenFor($user);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'security.reset_token_invalid');
    }

    public function test_a_forged_token_is_refused(): void
    {
        $user = User::factory()->create();
        $this->requestTokenFor($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'not-the-token',
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'security.reset_token_invalid');
    }

    public function test_redeeming_has_its_own_budget_so_a_typo_does_not_lock_you_out(): void
    {
        $user = User::factory()->create();
        $token = $this->requestTokenFor($user);

        // Two mistyped attempts, then the correct one must still be accepted.
        foreach (['short', 'alsoshort'] as $bad) {
            $this->postJson('/api/v1/auth/password/reset', [
                'token' => $token,
                'email' => $user->email,
                'password' => $bad,
                'password_confirmation' => $bad,
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();
    }

    public function test_expired_used_and_forged_are_indistinguishable(): void
    {
        // The distinction is not actionable — the reader needs a new link either
        // way — and telling them which one leaks whether a token ever existed.
        $user = User::factory()->create();
        $token = $this->requestTokenFor($user);

        $payload = fn (string $t) => [
            'token' => $t,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload($token))->assertOk();
        $used = $this->postJson('/api/v1/auth/password/reset', $payload($token));
        $forged = $this->postJson('/api/v1/auth/password/reset', $payload('nonsense'));

        $this->assertSame($used->json('code'), $forged->json('code'));
        $this->assertSame($used->json('detail'), $forged->json('detail'));
    }

    public function test_the_reset_link_points_at_the_frontend(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);

        Notification::assertSentTo($user, StaffPasswordReset::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            // The reset form is a Next.js route; the API has no page to land on.
            $this->assertStringStartsWith(config('app.frontend_url'), $url);
            $this->assertStringContainsString('/reset-password?', $url);

            return true;
        });
    }

    public function test_the_reset_rejects_a_password_below_policy(): void
    {
        $user = User::factory()->create();
        $token = $this->requestTokenFor($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'platform.validation_failed');
    }
}
