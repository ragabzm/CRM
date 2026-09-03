<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\User;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Portal\Domain\PortalAccount;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A customer cannot become staff, by any route.
 *
 * This is the single most important property in the story. Staff and customers
 * are different populations in different tables, and every other decision here
 * — a separate guard, a separate broker, a user shape with no roles — exists to
 * make the failure impossible rather than merely unlikely.
 *
 * The tests below are deliberately exhaustive about the DOORS rather than about
 * one of them: a boundary that holds at four endpoints and leaks at the fifth is
 * not a boundary.
 */
final class PortalCannotReachStaffTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private PortalAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->make(SettingsRegistry::class)->set('email.enabled', false, null);
        $this->setUpSpaOrigin();

        $this->account = new PortalAccount;
        $this->account->forceFill([
            'name' => 'Hana Yousef',
            'email' => 'hana@example.test',
            'password' => 'a-long-enough-passphrase',
            'preferred_locale' => 'en',
            'customer_id' => $this->makeCustomer(),
        ])->save();
    }

    /** @return list<array{string, string}> */
    public static function staffEndpoints(): array
    {
        return [
            ['GET', '/api/v1/users'],
            ['GET', '/api/v1/departments'],
            ['GET', '/api/v1/customers'],
            ['GET', '/api/v1/tickets'],
            ['GET', '/api/v1/tickets/counts'],
            ['GET', '/api/v1/admin/settings'],
            ['GET', '/api/v1/audit-entries'],
            ['GET', '/api/v1/notifications'],
            ['GET', '/api/v1/quick-replies'],
        ];
    }

    #[DataProvider('staffEndpoints')]
    public function test_a_portal_account_cannot_reach_a_staff_endpoint(string $method, string $uri): void
    {
        $response = $this->actingAs($this->account, 'portal')->json($method, $uri);

        /*
         * 401 or 403, never 200. The staff guard does not know this account
         * exists, so it is unauthenticated as far as those routes are
         * concerned — the capability layer never even gets a chance to refuse.
         */
        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            "{$method} {$uri} answered {$response->getStatusCode()} to a portal account.",
        );
    }

    public function test_the_staff_guard_cannot_resolve_a_portal_account_id(): void
    {
        /*
         * Both tables use auto-increment ids starting at 1, so a portal account
         * and a staff user routinely SHARE an id. That collision is real; what
         * makes it harmless is that each guard reads its own provider.
         *
         * Here there is no staff user at all, so the staff guard must refuse
         * an id the portal table happily owns. (`loginUsingId` returns `false`
         * rather than `null` on failure — Laravel's API, not this plan's.)
         */
        $this->assertSame(0, User::query()->count());

        $this->assertFalse(Auth::guard('web')->loginUsingId($this->account->getKey()));
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_the_two_guards_never_resolve_the_same_record(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        // Force the collision rather than hope for it.
        $this->assertSame((int) $agent->getKey(), (int) $this->account->getKey());

        $staff = Auth::guard('web')->loginUsingId($agent->getKey());
        Auth::guard('web')->logout();

        $portal = Auth::guard('portal')->loginUsingId($agent->getKey());
        Auth::guard('portal')->logout();

        /*
         * Same id, two entirely different people. An agent signing in cannot
         * appear on the portal as the customer who happens to share their id,
         * and vice versa — because the guards read different tables, not
         * because the numbers differ.
         */
        $this->assertInstanceOf(User::class, $staff);
        $this->assertInstanceOf(PortalAccount::class, $portal);
        $this->assertNotSame($staff->getAttribute('email'), $portal->getAttribute('email'));
    }

    public function test_a_portal_account_holds_no_roles(): void
    {
        /*
         * Not "holds an empty array of roles" — holds no such concept. An empty
         * array invites a frontend to branch on it, and a branch on an empty
         * array is one refactor away from becoming a branch that passes.
         */
        $this->assertFalse(method_exists($this->account, 'hasRole'));
        $this->assertFalse(method_exists($this->account, 'getAllPermissions'));
    }

    public function test_the_portal_user_shape_carries_nothing_from_the_staff_world(): void
    {
        $body = $this->actingAs($this->account, 'portal')
            ->getJson('/api/v1/portal/auth/me')
            ->assertOk()
            ->json();

        foreach (['roles', 'capabilities', 'permissions', 'department_id', 'is_active'] as $key) {
            $this->assertArrayNotHasKey($key, $body, "The portal shape leaked [{$key}].");
        }
    }

    public function test_a_staff_account_cannot_reach_the_portal_surface(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        // The portal scopes everything to the signed-in account's own customer.
        // A staff member has none, so there is nothing for them here.
        $this->actingAs($agent)->getJson('/api/v1/portal/tickets')->assertStatus(401);
    }

    public function test_signing_out_of_the_portal_ends_the_session(): void
    {
        $this->actingAs($this->account, 'portal')
            ->withIdempotencyKey()
            ->postJson('/api/v1/portal/auth/logout')
            ->assertOk();

        $this->assertNull(Auth::guard('portal')->user());
    }
}
