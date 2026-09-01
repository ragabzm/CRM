<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class UsersCrudTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrator = User::factory()->create();
        $administrator->syncRoles([Roles::ADMINISTRATOR]);
        $this->actingAs($administrator->refresh());
    }

    public function test_an_administrator_creates_a_user_with_a_role(): void
    {
        $response = $this->withIdempotencyKey()->postJson('/api/v1/users', [
            'name' => 'Nadia Salem',
            'email' => 'nadia@ragab.test',
            'role' => Roles::AGENT,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Nadia Salem')
            ->assertJsonPath('role', Roles::AGENT)
            ->assertJsonPath('is_active', true);

        $created = User::query()->where('email', 'nadia@ragab.test')->firstOrFail();
        $this->assertTrue($created->hasRole(Roles::AGENT));
    }

    public function test_a_created_user_gets_an_unusable_password_when_none_is_supplied(): void
    {
        $this->withIdempotencyKey()->postJson('/api/v1/users', [
            'name' => 'Nadia',
            'email' => 'nadia@ragab.test',
            'role' => Roles::AGENT,
        ])->assertStatus(201);

        $created = User::query()->where('email', 'nadia@ragab.test')->firstOrFail();

        // An administrator inventing a password and sending it over chat is
        // worse than a random one the person replaces via the reset flow.
        $this->assertNotEmpty($created->password);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('', $created->password));
    }

    public function test_the_response_never_carries_a_password(): void
    {
        $response = $this->withIdempotencyKey()->postJson('/api/v1/users', [
            'name' => 'Nadia',
            'email' => 'nadia@ragab.test',
            'role' => Roles::AGENT,
        ]);

        $this->assertStringNotContainsString('password', (string) $response->getContent());
    }

    public function test_a_user_can_be_assigned_to_a_department(): void
    {
        $department = Department::create(['name' => 'Billing', 'is_active' => true]);

        $this->withIdempotencyKey()->postJson('/api/v1/users', [
            'name' => 'Nadia',
            'email' => 'nadia@ragab.test',
            'role' => Roles::AGENT,
            'department_id' => $department->id,
        ])->assertStatus(201)->assertJsonPath('department_id', $department->id);
    }

    public function test_changing_a_role_replaces_it_rather_than_adding(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Roles::SUPERVISOR]);

        $this->withIdempotencyKey()->patchJson("/api/v1/users/{$user->id}", ['role' => Roles::AGENT])
            ->assertOk()
            ->assertJsonPath('role', Roles::AGENT);

        // A demotion must actually remove the old authority.
        $this->assertTrue($user->refresh()->hasRole(Roles::AGENT));
        $this->assertFalse($user->hasRole(Roles::SUPERVISOR));
        $this->assertCount(1, $user->roles);
    }

    public function test_a_duplicate_email_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@ragab.test']);

        $this->withIdempotencyKey()->postJson('/api/v1/users', [
            'name' => 'Someone',
            'email' => 'taken@ragab.test',
            'role' => Roles::AGENT,
        ])->assertStatus(422);
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $this->withIdempotencyKey()->postJson('/api/v1/users', [
            'name' => 'Someone',
            'email' => 'someone@ragab.test',
            'role' => 'superuser',
        ])->assertStatus(422);
    }

    public function test_deactivating_a_user_keeps_the_row_and_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Hana Yousef']);
        $user->syncRoles([Roles::AGENT]);

        $this->withIdempotencyKey()->postJson("/api/v1/users/{$user->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('name', 'Hana Yousef');

        /*
         * The row survives deactivation. Deleting it would break every
         * historical attribution pointing at it and turn an audit trail into a
         * set of orphan ids.
         */
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Hana Yousef', 'is_active' => false]);
    }

    public function test_deactivation_revokes_tokens_and_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('device');

        DB::table('sessions')->insert([
            'id' => 'session-for-user',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);
        config()->set('session.driver', 'database');

        $this->withIdempotencyKey()->postJson("/api/v1/users/{$user->id}/deactivate")->assertOk();

        // Tokens AND sessions: cookie mode means the session row is what
        // actually authenticates a browser.
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_setting_is_active_false_through_update_also_revokes(): void
    {
        $user = User::factory()->create();
        $user->createToken('device');

        $this->withIdempotencyKey()->patchJson("/api/v1/users/{$user->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_list_shows_deactivated_users_too(): void
    {
        $user = User::factory()->create(['name' => 'Departed Person', 'is_active' => false]);
        $user->syncRoles([Roles::AGENT]);

        $response = $this->getJson('/api/v1/users')->assertOk();

        // They must remain findable: their name still appears on historical
        // work, and an administrator needs to be able to reactivate them.
        $this->assertContains('Departed Person', array_column($response->json('data'), 'name'));
    }

    public function test_there_is_no_delete_route(): void
    {
        $user = User::factory()->create();

        $this->withIdempotencyKey()->deleteJson("/api/v1/users/{$user->id}")->assertStatus(405);
    }
}
