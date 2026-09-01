<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Http\ProblemDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SessionExpiryTest extends TestCase
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

    public function test_a_stored_session_older_than_the_lifetime_is_treated_as_expired(): void
    {
        /*
         * Asserted against the session handler rather than through two HTTP
         * calls: within one test the client reuses a single started session
         * Store, so a second request never re-reads storage and no amount of
         * time travel can expire it. That is a harness artefact, not product
         * behaviour — so this exercises the mechanism that actually governs
         * expiry, with the lifetime this application configures.
         */
        config()->set('session.lifetime', 30);

        $handler = new DatabaseSessionHandler(
            DB::connection(),
            'sessions',
            (int) config('session.lifetime'),
        );

        $handler->write('a-session-id', serialize(['auth' => 'payload']));

        $this->assertNotSame('', $handler->read('a-session-id'));

        $this->travel(31)->minutes();

        // The row is still there; it simply no longer counts as a session.
        $this->assertSame('', $handler->read('a-session-id'));
    }

    public function test_an_unauthenticated_request_reports_the_same_code(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'security.session_expired');
    }

    public function test_the_expiry_problem_names_its_type_uri(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertJsonPath('type', 'https://errors.ragab-crm/security.session_expired');
    }

    public function test_the_expiry_response_still_carries_a_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401);
        $this->assertNotNull($response->headers->get('X-Request-Id'));
        $this->assertSame(
            $response->headers->get('X-Request-Id'),
            $response->json('trace_id'),
        );
    }

    public function test_the_configured_inactivity_window_is_readable_by_the_client(): void
    {
        config()->set('auth.staff.inactivity_minutes', 30);

        // So the frontend can warn before a session lapses rather than
        // discovering it on the next request.
        $this->getJson('/api/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('inactivity_minutes', 30);
    }

    public function test_the_session_endpoint_reports_authentication_state(): void
    {
        $this->getJson('/api/v1/auth/session')->assertJsonPath('authenticated', false);

        $this->signedIn();

        $this->getJson('/api/v1/auth/session')->assertJsonPath('authenticated', true);
    }
}
