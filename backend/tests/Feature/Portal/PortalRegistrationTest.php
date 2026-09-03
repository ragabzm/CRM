<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Portal\Domain\PortalAccount;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Opening a portal account.
 *
 * The linking is the part worth testing hardest: somebody who has emailed
 * support before is already in the database, and a portal that showed them none
 * of their own history would read as the product having lost it.
 */
final class PortalRegistrationTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->app->make(SettingsRegistry::class)->set('email.enabled', false, null);

        /*
         * The Origin a browser sends. Sanctum only attaches a session to an API
         * request from a stateful domain — that gate is the point of cookie
         * mode — so the test presents the origin rather than switching it off.
         */
        $this->setUpSpaOrigin();

        // The limiters are the subject of their own test; everywhere else they
        // would just make the suite flaky.
        RateLimiter::clear('portal-register');
    }

    private function register(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        /*
         * The key the real client sends. `request()` in the frontend adds one to
         * every write without the caller remembering, so a test that omitted it
         * would be exercising a path no browser takes.
         */
        return $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/register', [
            'name' => 'Hana Yousef',
            'email' => 'hana@example.test',
            'password' => 'a-long-enough-passphrase',
            'password_confirmation' => 'a-long-enough-passphrase',
            'preferred_locale' => 'ar',
            ...$overrides,
        ]);
    }

    public function test_a_customer_can_open_an_account(): void
    {
        $this->register()->assertStatus(201)->assertJsonPath('email', 'hana@example.test');

        $this->assertSame(1, PortalAccount::query()->count());
    }

    public function test_registering_signs_them_in(): void
    {
        $this->register()->assertStatus(201);

        // Making somebody register and then sign in asks them to prove twice
        // what they just proved once.
        $this->assertNotNull(auth('portal')->user());
    }

    public function test_it_links_to_a_customer_the_business_already_knows(): void
    {
        $existing = $this->makeCustomer();

        DB::table('contact_identifiers')->insert([
            'id' => (string) Str::ulid(),
            'customer_id' => $existing,
            'kind' => 'email',
            'value' => 'Hana@Example.test',
            'value_normalised' => 'hana@example.test',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('customers')->count();

        $this->register()->assertStatus(201)->assertJsonPath('customer_id', $existing);

        /*
         * Matched case-insensitively, and no second customer created. Somebody
         * whose history is split across two records has a portal that shows
         * them half of it.
         */
        $this->assertSame($before, DB::table('customers')->count());
    }

    public function test_it_creates_a_customer_when_nobody_matches(): void
    {
        $this->register()->assertStatus(201);

        $customer = DB::table('customers')->first();

        $this->assertSame('Hana Yousef', $customer->full_name);
        $this->assertSame('portal_registration', $customer->created_via);
    }

    public function test_it_remembers_the_language_they_chose(): void
    {
        $this->register(['preferred_locale' => 'ar'])->assertStatus(201);

        // Their own choice, on a form — not what an agent guessed about them.
        $this->assertSame('ar', PortalAccount::query()->value('preferred_locale'));
    }

    public function test_it_defaults_to_english_when_they_did_not_say(): void
    {
        $this->register(['preferred_locale' => null])->assertStatus(201);

        $this->assertSame('en', PortalAccount::query()->first()->preferredLocale());
    }

    public function test_one_address_cannot_register_twice(): void
    {
        $this->register()->assertStatus(201);

        RateLimiter::clear('portal-register');

        $this->register()->assertStatus(422);
    }

    public function test_the_same_address_in_different_case_is_the_same_person(): void
    {
        $this->register()->assertStatus(201);
        RateLimiter::clear('portal-register');

        // Letting both register would split their requests across two accounts
        // neither of which shows the whole picture.
        $this->register(['email' => 'HANA@Example.test'])->assertStatus(422);
    }

    public function test_a_short_password_is_refused(): void
    {
        // Length is what costs an attacker time; a character-class puzzle just
        // pushes people toward `Passw0rd!`.
        $this->register(['password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422);
    }

    public function test_a_mistyped_confirmation_is_refused(): void
    {
        $this->register(['password_confirmation' => 'something-else'])->assertStatus(422);
    }

    public function test_the_response_carries_no_roles_or_capabilities(): void
    {
        $body = $this->register()->assertStatus(201)->json();

        /*
         * A portal account holds none of these. Publishing empty ones would
         * invite a frontend to branch on them — the first step toward a
         * customer being shown a staff surface because an array happened to be
         * empty rather than absent.
         */
        $this->assertArrayNotHasKey('roles', $body);
        $this->assertArrayNotHasKey('capabilities', $body);
        $this->assertArrayNotHasKey('department_id', $body);
    }

    public function test_the_password_never_comes_back(): void
    {
        $response = $this->register()->assertStatus(201);

        $this->assertStringNotContainsString('a-long-enough-passphrase', (string) $response->getContent());
        $this->assertArrayNotHasKey('password', $response->json());
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->register(['email' => "person{$i}@example.test"]);
        }

        // Registration is a once-ever act for a given person; anything faster
        // than this is a script.
        $this->register(['email' => 'another@example.test'])->assertStatus(429);
    }
}
