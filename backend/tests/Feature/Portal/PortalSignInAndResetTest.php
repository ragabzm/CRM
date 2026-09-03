<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Portal\Domain\PortalAccount;
use App\Modules\Portal\Notifications\PortalPasswordReset;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Signing in, and getting back in.
 */
final class PortalSignInAndResetTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-passphrase';

    private PortalAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->make(SettingsRegistry::class)->set('email.enabled', false, null);
        $this->setUpSpaOrigin();

        RateLimiter::clear('portal-login');
        RateLimiter::clear('portal-password');

        $this->account = new PortalAccount;
        $this->account->forceFill([
            'name' => 'Hana Yousef',
            'email' => 'hana@example.test',
            'password' => self::PASSWORD,
            'preferred_locale' => 'ar',
            'customer_id' => $this->makeCustomer(),
        ])->save();
    }

    private function login(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/login', [
            'email' => 'hana@example.test',
            'password' => self::PASSWORD,
            ...$overrides,
        ]);
    }

    public function test_a_customer_can_sign_in(): void
    {
        $this->login()->assertOk()->assertJsonPath('email', 'hana@example.test');

        $this->assertNotNull(Auth::guard('portal')->user());
    }

    public function test_the_address_is_matched_case_insensitively(): void
    {
        // People capitalise their own address. Refusing them would be refusing
        // the account they know they have.
        $this->login(['email' => 'HANA@Example.test'])->assertOk();
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->login(['password' => 'not-the-password'])->assertStatus(401);
    }

    public function test_an_unknown_address_gets_the_same_answer_as_a_wrong_password(): void
    {
        $unknown = $this->login(['email' => 'nobody@example.test'])->assertStatus(401);
        RateLimiter::clear('portal-login');
        $wrong = $this->login(['password' => 'not-the-password'])->assertStatus(401);

        /*
         * Identical. Telling them apart turns sign-in into a way to discover
         * which addresses have accounts — and the answer is worth having,
         * because it says who is a customer of this business.
         */
        $this->assertSame($unknown->json('detail'), $wrong->json('detail'));
        $this->assertSame($unknown->json('code'), $wrong->json('code'));
    }

    public function test_sign_in_is_rate_limited_per_address(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login(['password' => 'wrong']);
        }

        // A person who has forgotten their password tries three or four times,
        // not sixty.
        $this->login()->assertStatus(429);
    }

    public function test_signing_out_clears_the_session(): void
    {
        $this->login()->assertOk();

        $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/logout')->assertOk();

        $this->assertNull(Auth::guard('portal')->user());
    }

    public function test_a_reset_link_is_emailed(): void
    {
        Notification::fake();

        $this->withIdempotencyKey()
            ->postJson('/api/v1/portal/auth/password/forgot', ['email' => 'hana@example.test'])
            ->assertOk();

        Notification::assertSentTo($this->account, PortalPasswordReset::class);
    }

    public function test_an_unknown_address_gets_the_same_answer(): void
    {
        Notification::fake();

        $response = $this->withIdempotencyKey()
            ->postJson('/api/v1/portal/auth/password/forgot', ['email' => 'nobody@example.test'])
            ->assertOk();

        /*
         * Same 200, same body. A different response for a known address turns
         * this into a way to discover who is a customer — and this endpoint is
         * deliberately unauthenticated, so anybody can ask.
         */
        $this->assertSame(['status' => 'sent'], $response->json());
        Notification::assertNothingSent();
    }

    public function test_the_link_points_at_the_portal_not_the_staff_form(): void
    {
        Notification::fake();

        $this->withIdempotencyKey()
            ->postJson('/api/v1/portal/auth/password/forgot', ['email' => 'hana@example.test'])
            ->assertOk();

        Notification::assertSentTo($this->account, PortalPasswordReset::class, function ($notification): bool {
            $url = (string) $notification->toMail($this->account)->actionUrl;

            // Laravel's default would send a customer to a sign-in screen built
            // for agents, which they cannot use.
            return str_contains($url, '/portal/reset-password');
        });
    }

    public function test_the_email_is_in_the_customers_own_language(): void
    {
        $mail = (new PortalPasswordReset('token', 'hana@example.test'))->toMail($this->account);

        // Somebody who registered in Arabic and receives English instructions
        // for recovering their account has been locked out twice.
        $this->assertStringContainsString('إعادة تعيين', (string) $mail->subject);
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        $token = Password::broker('portal_accounts')->createToken($this->account);

        $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/password/reset', [
            'token' => $token,
            'email' => 'hana@example.test',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertOk();

        RateLimiter::clear('portal-login');
        $this->login(['password' => 'a-brand-new-passphrase'])->assertOk();
    }

    public function test_a_token_cannot_be_used_twice(): void
    {
        $token = Password::broker('portal_accounts')->createToken($this->account);

        $body = [
            'token' => $token,
            'email' => 'hana@example.test',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ];

        $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/password/reset', $body)->assertOk();

        RateLimiter::clear('portal-password');

        /*
         * Single use. A reset link sits in an inbox forever; one that still
         * worked a month later would be a permanent key to the account, and
         * inboxes are exactly what gets compromised.
         */
        $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/password/reset', $body)
            ->assertStatus(422)
            ->assertJsonPath('code', 'portal.invalid_reset_token');
    }

    public function test_a_forged_token_is_refused(): void
    {
        $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/password/reset', [
            'token' => 'not-a-real-token',
            'email' => 'hana@example.test',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertStatus(422);
    }

    public function test_a_reset_invalidates_a_remembered_session(): void
    {
        $before = $this->account->getAttribute('remember_token');

        $token = Password::broker('portal_accounts')->createToken($this->account);

        $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/password/reset', [
            'token' => $token,
            'email' => 'hana@example.test',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertOk();

        /*
         * Somebody resetting a password is often doing it BECAUSE another
         * person has their session. Leaving a "remember me" cookie working
         * would leave that person signed in.
         */
        $this->assertNotSame($before, $this->account->refresh()->getAttribute('remember_token'));
    }

    public function test_the_portal_broker_is_separate_from_the_staff_one(): void
    {
        Password::broker('portal_accounts')->createToken($this->account);

        /*
         * Different token table. A shared one would mean a token issued for a
         * customer could be spent against a staff account with the same
         * address — and support desks are exactly where one person often has
         * both.
         */
        $this->assertSame(1, DB::table('portal_password_reset_tokens')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_the_forgot_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->withIdempotencyKey()
                ->postJson('/api/v1/portal/auth/password/forgot', ['email' => 'hana@example.test']);
        }

        /*
         * Every request here sends an EMAIL, so an unthrottled endpoint is a
         * way to use this system to flood somebody else's inbox.
         */
        $this->withIdempotencyKey()
            ->postJson('/api/v1/portal/auth/password/forgot', ['email' => 'hana@example.test'])
            ->assertStatus(429);
    }
}
