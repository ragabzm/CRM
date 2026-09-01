<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Http\ProblemDetails;
use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The four refusals the intake names, end to end over HTTP.
 *
 * These go through the real middleware stack rather than asking the Gate
 * directly: "hiding the control is not enforcement" is a claim about what the
 * SERVER does when someone calls the endpoint anyway.
 */
final class AuthorizationRefusalTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function signedInAs(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $this->actingAs($user->refresh())->user ?? $user;
    }

    private function assertRefusal(TestResponse $response, string $capability): void
    {
        $response->assertStatus(403);

        $this->assertStringStartsWith(
            ProblemDetails::CONTENT_TYPE,
            (string) $response->headers->get('Content-Type'),
            'A refusal must be a problem document, never an empty body.',
        );

        $response->assertJsonPath('code', 'security.forbidden');
        $response->assertJsonPath('title', 'Forbidden');

        // What was refused, and who to ask. A bare 403 leaves the reader with
        // no next step and produces a support ticket.
        $response->assertJsonPath('capability', $capability);
        $response->assertJsonPath('contact', 'administrator');

        $this->assertNotEmpty($response->json('detail'));
        $this->assertStringContainsString($capability, (string) $response->json('detail'));
    }

    public function test_an_agent_cannot_reassign_a_ticket(): void
    {
        $this->signedInAs(Roles::AGENT);

        $this->assertRefusal(
            $this->withIdempotencyKey()->postJson('/api/v1/tickets/1/reassign'),
            Capabilities::TICKET_REASSIGN,
        );
    }

    public function test_an_agent_cannot_manage_users(): void
    {
        $this->signedInAs(Roles::AGENT);

        $this->assertRefusal($this->getJson('/api/v1/users'), Capabilities::USER_MANAGE);
    }

    public function test_a_supervisor_cannot_change_configuration(): void
    {
        $this->signedInAs(Roles::SUPERVISOR);

        $this->assertRefusal(
            $this->withIdempotencyKey()->putJson('/api/v1/settings/branding'),
            Capabilities::SETTING_MANAGE,
        );
    }

    public function test_a_supervisor_cannot_read_the_audit_log(): void
    {
        $this->signedInAs(Roles::SUPERVISOR);

        $this->assertRefusal($this->getJson('/api/v1/audit'), Capabilities::AUDIT_READ);
    }

    public function test_an_agent_cannot_manage_departments(): void
    {
        $this->signedInAs(Roles::AGENT);

        $this->assertRefusal(
            $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => 'Billing']),
            Capabilities::DEPARTMENT_MANAGE,
        );
    }

    public function test_a_customer_cannot_reach_any_staff_surface(): void
    {
        $this->signedInAs(Roles::CUSTOMER);

        $this->getJson('/api/v1/users')->assertStatus(403);
        $this->getJson('/api/v1/departments')->assertStatus(403);
        $this->getJson('/api/v1/audit')->assertStatus(403);
    }

    public function test_an_administrator_passes_every_one_of_them(): void
    {
        $this->signedInAs(Roles::ADMINISTRATOR);

        $this->getJson('/api/v1/users')->assertOk();
        $this->getJson('/api/v1/departments')->assertOk();
        $this->getJson('/api/v1/audit')->assertOk();
        $this->withIdempotencyKey()->putJson('/api/v1/settings/branding')->assertOk();
        $this->withIdempotencyKey()->postJson('/api/v1/tickets/1/reassign')->assertOk();
    }

    public function test_a_refusal_carries_a_correlation_id(): void
    {
        $this->signedInAs(Roles::AGENT);

        $response = $this->getJson('/api/v1/users');

        $this->assertNotNull($response->headers->get('X-Request-Id'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('trace_id'));
    }

    public function test_an_anonymous_caller_is_refused_before_the_capability_check(): void
    {
        // Not 403: an unauthenticated caller has no capabilities to be missing.
        $this->getJson('/api/v1/users')->assertStatus(401);
    }

    public function test_a_route_naming_an_unknown_capability_fails_loudly(): void
    {
        // A typo would otherwise fail closed and look like working security
        // until an administrator reports being locked out.
        $middleware = new \App\Modules\Security\Http\Middleware\RequireCapability;

        $this->expectException(\InvalidArgumentException::class);

        $middleware->handle(
            \Illuminate\Http\Request::create('/api/v1/anything'),
            fn () => new \Illuminate\Http\JsonResponse([]),
            'ticket.reasign',
        );
    }
}
