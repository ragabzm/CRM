<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Http\ProblemDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class LoginRateLimitTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        RateLimiter::clear('login');
    }

    public function test_the_sixth_attempt_in_a_minute_is_refused(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct-Horse-9')]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $this->assertStringStartsWith(
            ProblemDetails::CONTENT_TYPE,
            (string) $response->headers->get('Content-Type'),
        );
        $response->assertJsonPath('code', 'platform.too_many_requests');
    }

    public function test_the_limit_holds_even_once_the_password_is_correct(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct-Horse-9')]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // Otherwise the limiter is only an inconvenience: guess five times, then
        // the winning guess sails through.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Correct-Horse-9',
        ])->assertStatus(429);
    }

    public function test_one_account_being_attacked_does_not_lock_out_another(): void
    {
        $victim = User::factory()->create(['password' => Hash::make('Correct-Horse-9')]);
        $bystander = User::factory()->create(['password' => Hash::make('Another-Horse-8')]);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $victim->email,
                'password' => 'wrong-password',
            ]);
        }

        // Keyed on email AND ip: keying on ip alone would let one attacker
        // behind a NAT lock out a whole office.
        $this->postJson('/api/v1/auth/login', [
            'email' => $bystander->email,
            'password' => 'Another-Horse-8',
        ])->assertOk();
    }

    public function test_the_reset_endpoint_is_limited_separately(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
                ->assertStatus(202);
        }

        // Three in ten minutes: each request sends mail to a real inbox.
        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertStatus(429);
    }
}
