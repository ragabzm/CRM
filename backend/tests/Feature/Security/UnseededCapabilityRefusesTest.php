<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A capability that exists in code but not yet in the database refuses. It
 * does not crash.
 *
 * Every deployment passes through this state: the code declaring a new
 * capability ships, and the seeder that grants it runs a moment later. In
 * between, spatie's `$user->can()` THROWS `PermissionDoesNotExist` rather than
 * answering false — which turned the endpoint into a 500 for what is really
 * "nobody holds this yet".
 *
 * Found while adding `ticket.escalate` in Story 10.1: the route answered 500
 * on a development database whose seeder had not been re-run.
 */
final class UnseededCapabilityRefusesTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    public function test_an_endpoint_refuses_rather_than_failing_when_its_capability_is_unseeded(): void
    {
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles([Roles::AGENT]);
        $this->actingAs($user->refresh());

        /*
         * Exactly the deployment window: the constant is in PHP, the row is
         * not in the database. Removed after the roles are seeded so the
         * grants that referenced it are gone too.
         */
        DB::table('role_has_permissions')->whereIn(
            'permission_id',
            DB::table('permissions')->where('name', Capabilities::TICKET_ESCALATE)->pluck('id'),
        )->delete();

        DB::table('permissions')->where('name', Capabilities::TICKET_ESCALATE)->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        /*
         * A REAL ticket, so a 404 cannot stand in for the refusal. Against a
         * made-up id this test passed the middleware and failed at the
         * controller, which would have looked like the same green tick while
         * proving nothing.
         */
        $ticket = $this->makeTicket();

        $response = $this->withIdempotencyKey()->postJson(
            '/api/v1/tickets/'.$ticket->getKey().'/escalate',
            ['reason' => 'Anything at all.'],
        );

        // 403 with a body that names what was refused — not a 500 in the logs.
        $response->assertForbidden();
        $this->assertSame('security.forbidden', $response->json('code'));
        $this->assertSame(Capabilities::TICKET_ESCALATE, $response->json('capability'));
    }

    public function test_a_capability_that_does_not_exist_in_php_at_all_still_fails_loudly(): void
    {
        /*
         * The typo case is NOT swallowed. `can.capability:tickets.reasign`
         * would refuse everybody, which looks like working security until an
         * administrator reports being locked out — so it throws at the
         * middleware, before any of this.
         */
        $this->assertFalse(Capabilities::exists('tickets.reasign'));
        $this->assertTrue(Capabilities::exists(Capabilities::TICKET_ESCALATE));
    }
}
