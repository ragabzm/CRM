<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Portal\Domain\PortalAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Two identity spaces, two guards, two tables.
 *
 * The acceptance criterion is that "a credential valid in one space is
 * meaningless in the other". These tests assert the stronger property that
 * actually holds: the wrong guard cannot even SEE the row, because it queries a
 * different table. That is why there is no `is_staff` column — a flag is a value
 * someone can set wrong, while a separate table makes the question unanswerable
 * in the wrong direction.
 */
final class GuardIsolationTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
    }

    private function staff(): User
    {
        return User::factory()->create([
            'email' => 'staff@ragab.test',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    private function customer(): PortalAccount
    {
        return PortalAccount::create([
            'name' => 'Customer',
            'email' => 'customer@ragab.test',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    public function test_the_two_identity_spaces_are_separate_tables(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('portal_accounts'));

        // No discriminator anywhere: the separation is structural.
        $this->assertFalse(Schema::hasColumn('users', 'is_staff'));
        $this->assertFalse(Schema::hasColumn('users', 'type'));
        $this->assertFalse(Schema::hasColumn('portal_accounts', 'is_staff'));
    }

    public function test_a_staff_credential_is_meaningless_to_the_portal_guard(): void
    {
        $staff = $this->staff();

        $this->assertFalse(
            Auth::guard('portal')->attempt(['email' => $staff->email, 'password' => self::PASSWORD]),
        );

        // Not merely rejected — invisible. The portal provider queries
        // portal_accounts, where this row does not exist.
        $this->assertNull(
            Auth::guard('portal')->getProvider()->retrieveByCredentials(['email' => $staff->email]),
        );
    }

    public function test_a_portal_credential_is_meaningless_to_the_staff_guard(): void
    {
        $customer = $this->customer();

        $this->assertFalse(
            Auth::guard('web')->attempt(['email' => $customer->email, 'password' => self::PASSWORD]),
        );

        $this->assertNull(
            Auth::guard('web')->getProvider()->retrieveByCredentials(['email' => $customer->email]),
        );
    }

    public function test_a_portal_credential_cannot_sign_in_at_the_staff_endpoint(): void
    {
        $customer = $this->customer();

        $this->postJson('/api/v1/auth/login', [
            'email' => $customer->email,
            'password' => self::PASSWORD,
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'security.invalid_credentials');
    }

    public function test_the_same_address_can_exist_in_both_spaces_independently(): void
    {
        $shared = 'same@ragab.test';

        User::factory()->create(['email' => $shared, 'password' => Hash::make('Staff-Password-1')]);
        PortalAccount::create([
            'name' => 'Customer',
            'email' => $shared,
            'password' => Hash::make('Portal-Password-2'),
        ]);

        // One human may be both an employee and a customer. Each password works
        // only in its own space; neither leaks into the other.
        $this->assertTrue(
            Auth::guard('web')->attempt(['email' => $shared, 'password' => 'Staff-Password-1']),
        );
        $this->assertFalse(
            Auth::guard('web')->attempt(['email' => $shared, 'password' => 'Portal-Password-2']),
        );
        $this->assertTrue(
            Auth::guard('portal')->attempt(['email' => $shared, 'password' => 'Portal-Password-2']),
        );
        $this->assertFalse(
            Auth::guard('portal')->attempt(['email' => $shared, 'password' => 'Staff-Password-1']),
        );
    }

    public function test_each_space_has_its_own_reset_token_table(): void
    {
        // A token minted for a customer must not be redeemable against a staff
        // account. Separate tables make that structural rather than a WHERE
        // clause someone can forget.
        $this->assertSame('password_reset_tokens', config('auth.passwords.users.table'));
        $this->assertSame('portal_password_reset_tokens', config('auth.passwords.portal_accounts.table'));
        $this->assertTrue(Schema::hasTable('portal_password_reset_tokens'));
    }

    public function test_the_guards_resolve_to_different_providers(): void
    {
        $this->assertSame('users', config('auth.guards.web.provider'));
        $this->assertSame('portal_accounts', config('auth.guards.portal.provider'));
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertSame(PortalAccount::class, config('auth.providers.portal_accounts.model'));
    }
}
