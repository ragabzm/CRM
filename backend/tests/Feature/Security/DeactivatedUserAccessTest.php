<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Http\ProblemDetails;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deactivation bites on the next request.
 *
 * Deleting sessions and tokens at deactivation time is necessary but not
 * sufficient — a session this process did not delete, or a request already in
 * flight, would otherwise sail through until it expired.
 */
final class DeactivatedUserAccessTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agent(bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $user->syncRoles([Roles::AGENT]);

        return $user->refresh();
    }

    public function test_an_active_user_is_served(): void
    {
        $this->actingAs($this->agent());

        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_a_deactivated_user_is_refused_on_the_next_request(): void
    {
        $user = $this->agent();
        $this->actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
        $this->assertStringStartsWith(
            ProblemDetails::CONTENT_TYPE,
            (string) $response->headers->get('Content-Type'),
        );
        $response->assertJsonPath('code', 'security.account_deactivated');
        $response->assertJsonPath('contact', 'administrator');
    }

    public function test_the_refusal_is_401_not_403(): void
    {
        $user = $this->agent(false);
        $this->actingAs($user);

        // A deactivated account cannot act at all — this is a statement about
        // the session, not about a missing permission.
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_a_deactivated_user_cannot_reach_any_guarded_surface(): void
    {
        $user = $this->agent(false);
        $this->actingAs($user);

        foreach (['/api/v1/auth/me', '/api/v1/profile', '/api/v1/users'] as $path) {
            $this->getJson($path)->assertStatus(401);
        }
    }

    public function test_a_deactivated_user_can_still_sign_in_attempt_and_be_refused(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'password' => \Illuminate\Support\Facades\Hash::make('Correct-Horse-9'),
        ]);
        $user->syncRoles([Roles::AGENT]);

        // Credentials are valid, so sign-in itself succeeds — and the very next
        // guarded call is refused. Blocking at the credential check would leak
        // that the account exists but is disabled.
        $this->withIdempotencyKey()->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Correct-Horse-9',
        ])->assertStatus(401)->assertJsonPath('code', 'security.account_deactivated');
    }

    public function test_historical_attribution_survives_deactivation(): void
    {
        $author = User::factory()->create(['name' => 'Hana Yousef']);
        $author->syncRoles([Roles::AGENT]);

        $administrator = User::factory()->create();
        $administrator->syncRoles([Roles::ADMINISTRATOR]);
        $this->actingAs($administrator->refresh());

        $this->withIdempotencyKey()->postJson("/api/v1/users/{$author->id}/deactivate")->assertOk();

        /*
         * The name is what a reader sees on a note written years ago. Deleting
         * the row would turn every such reference into an orphan id.
         */
        $listed = collect($this->getJson('/api/v1/users')->json('data'))
            ->firstWhere('id', $author->id);

        $this->assertSame('Hana Yousef', $listed['name']);
        $this->assertFalse($listed['is_active']);
        $this->assertDatabaseHas('users', ['id' => $author->id, 'name' => 'Hana Yousef']);
    }
}
