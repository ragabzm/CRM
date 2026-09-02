<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Audit;

use App\Models\User;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class AuditReadAuthorizationTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->app->make(AuditWriter::class)->record(
            action: AuditAction::ConfigChanged,
            targetType: 'setting',
            targetId: 'tickets.auto_close_hours',
        );
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());

        return $user;
    }

    public function test_an_administrator_reads_the_log(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->getJson('/api/v1/audit-entries')
            ->assertOk()
            ->assertJsonPath('data.0.action', AuditAction::ConfigChanged->value)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_a_supervisor_is_refused(): void
    {
        $this->actingAsRole(Roles::SUPERVISOR);

        // A supervisor manages people. Reading every action every colleague has
        // taken is a different power, and it is not one the role carries.
        $this->getJson('/api/v1/audit-entries')
            ->assertStatus(403)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['type', 'title', 'status', 'code']);
    }

    public function test_an_agent_is_refused(): void
    {
        $this->actingAsRole(Roles::AGENT);

        $this->getJson('/api/v1/audit-entries')->assertStatus(403);
    }

    public function test_a_guest_is_challenged_rather_than_forbidden(): void
    {
        // 401, not 403: the difference tells the SPA to offer a sign-in rather
        // than a "you cannot do this" that signing in would have fixed.
        $this->getJson('/api/v1/audit-entries')->assertStatus(401);
    }

    public function test_a_refused_reader_learns_nothing_about_the_contents(): void
    {
        $this->actingAsRole(Roles::AGENT);

        $body = (string) $this->getJson('/api/v1/audit-entries')->getContent();

        // Not even a count. "403, and there are 4,812 entries" is still a leak.
        $this->assertStringNotContainsString('tickets.auto_close_hours', $body);
        $this->assertStringNotContainsString('meta', $body);
    }

    public function test_a_single_entry_is_gated_the_same_way(): void
    {
        $administrator = $this->actingAsRole(Roles::ADMINISTRATOR);
        $id = (string) $this->getJson('/api/v1/audit-entries')->json('data.0.id');

        $this->assertNotSame('', $id);

        $agent = User::factory()->create();
        $agent->syncRoles([Roles::AGENT]);
        $this->actingAs($agent->refresh());

        // The list and the detail are one permission, not two — a gate applied
        // to only the index is a gate someone routes around.
        $this->getJson("/api/v1/audit-entries/{$id}")->assertStatus(403);
        $this->assertNotNull($administrator);
    }
}
