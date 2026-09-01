<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Http\ProblemDetails;
use App\Modules\Security\Events\StaffAuthAttempted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginFailureTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
    }

    public function test_a_wrong_password_is_a_problem_document(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct-Horse-9')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $this->assertStringStartsWith(
            ProblemDetails::CONTENT_TYPE,
            (string) $response->headers->get('Content-Type'),
        );
        $response->assertJsonPath('code', 'security.invalid_credentials');
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_an_unknown_account_is_indistinguishable_from_a_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct-Horse-9')]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $unknownAccount = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@ragab.test',
            'password' => 'wrong-password',
        ]);

        // Distinguishing the two turns the sign-in form into an account
        // enumeration oracle.
        $this->assertSame($wrongPassword->getStatusCode(), $unknownAccount->getStatusCode());
        $this->assertSame(
            $wrongPassword->json('code'),
            $unknownAccount->json('code'),
        );
        $this->assertSame($wrongPassword->json('detail'), $unknownAccount->json('detail'));
    }

    public function test_a_failure_is_recorded_in_the_audit_trail(): void
    {
        Event::fake([StaffAuthAttempted::class]);
        $user = User::factory()->create(['password' => Hash::make('Correct-Horse-9')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        // A failure nobody records is a brute force nobody can see.
        Event::assertDispatched(
            StaffAuthAttempted::class,
            fn (StaffAuthAttempted $event) => $event->outcome === StaffAuthAttempted::OUTCOME_FAILURE
                && $event->email === $user->email
                && $event->userId === null,
        );
    }

    public function test_the_audit_event_carries_no_credential(): void
    {
        $event = StaffAuthAttempted::failure('agent@ragab.test', '127.0.0.1', 'Firefox');

        // A value that is never in the object cannot be leaked by a listener
        // that logs the whole event.
        $serialised = json_encode(get_object_vars($event), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('password', strtolower($serialised));
    }

    public function test_a_malformed_request_is_a_validation_problem(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'platform.validation_failed');
    }

    public function test_the_login_password_is_not_judged_against_the_policy(): void
    {
        // The policy governs passwords being SET. Applying it at sign-in would
        // tell an attacker the shape of a valid password before they guess one,
        // and would lock out anyone whose password predates a policy change.
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.test', 'password' => 'short'])
            ->assertStatus(401);
    }
}
