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

    /** A well-formed ULID for a ticket that deliberately does not exist. */
    private const SOME_ULID = '01JZZZZZZZZZZZZZZZZZZZZZZZ';

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

    public function test_an_agent_cannot_take_a_ticket_a_colleague_is_holding(): void
    {
        /*
         * Story 4.2 split assignment in two, so this refusal moved from the
         * route to the command. An agent MAY assign — picking up unclaimed
         * work is most of their day — but taking a ticket out of a colleague's
         * hands needs `ticket.reassign_any`, which only a supervisor holds.
         *
         * Asserted through the real endpoint with a real ticket, because the
         * rule depends on who currently holds it and a capability check alone
         * could not express that.
         */
        $colleague = User::factory()->create();
        $colleague->syncRoles([Roles::AGENT]);

        $ticket = $this->ticketHeldBy($colleague);

        $this->signedInAs(Roles::AGENT);

        $response = $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$ticket}/assign", [
            'version' => 1,
            'assignee_id' => $this->currentUserId(),
        ])->assertStatus(403);

        $response->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'tickets.reassign_forbidden');

        // The refusal says who to ask, not just no.
        $this->assertStringContainsString('supervisor', (string) $response->json('detail'));
    }

    /** A ticket already assigned to someone else. */
    private function ticketHeldBy(User $holder): string
    {
        $department = \App\Modules\Security\Domain\Department::firstOrCreate(
            ['name' => 'Billing'],
            ['is_active' => true],
        );

        $customer = new \App\Modules\Customers\Domain\Customer([
            'reference' => \App\Modules\Customers\Domain\Customer::mintReference(),
            'full_name' => 'Hana Yousef',
            'department_id' => $department->getKey(),
            'state' => 'active',
        ]);
        $customer->setAttribute('id', (string) \Illuminate\Support\Str::ulid());
        $customer->save();

        $ticket = $this->app->make(\App\Modules\Tickets\Domain\Commands\CreateTicket::class)->handle(
            \App\Modules\Tickets\Domain\Actor\Actor::staff((string) $holder->getKey(), 'Colleague'),
            new \App\Modules\Tickets\Domain\Commands\CreateTicketInput(
                subject: 'Held by someone else',
                description: 'Mid-conversation.',
                customerId: (string) $customer->getKey(),
                channel: \App\Modules\Tickets\Domain\Enum\TicketChannel::Agent,
            ),
        );

        $ticket->forceFill(['assignee_id' => $holder->getKey()])->save();

        return (string) $ticket->getKey();
    }

    private function currentUserId(): int
    {
        return (int) auth()->id();
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

        /*
         * Asserted as "not refused" rather than 200: this ticket does not
         * exist, so the honest answer is 404 — which is still proof the
         * capability gate let the administrator through.
         */
        $this->withIdempotencyKey()->postJson('/api/v1/tickets/'.self::SOME_ULID.'/assign', [
            'version' => 1,
            'assignee_id' => null,
        ])->assertStatus(404);
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
