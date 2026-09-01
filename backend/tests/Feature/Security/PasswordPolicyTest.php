<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Security\Rules\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class PasswordPolicyTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private const CURRENT = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
    }

    private function signedIn(): User
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT)]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::CURRENT,
        ])->assertOk();

        return $user;
    }

    /** @return list<string> */
    private function failuresFor(string $password): array
    {
        $validator = Validator::make(['password' => $password], ['password' => [new PasswordPolicy]]);

        return $validator->errors()->get('password');
    }

    public function test_the_policy_is_configuration_not_a_hard_coded_number(): void
    {
        config()->set('auth.password_policy.min_length', 20);

        $this->assertNotEmpty($this->failuresFor('Short-But-Valid1'));

        config()->set('auth.password_policy.min_length', 8);

        $this->assertEmpty($this->failuresFor('Short-Bt1'));
    }

    public function test_it_rejects_a_password_below_the_minimum_length(): void
    {
        $this->assertNotEmpty($this->failuresFor('Ab1cdef'));
    }

    public function test_it_requires_each_enabled_character_class(): void
    {
        $this->assertNotEmpty($this->failuresFor('alllowercaseletters1'));
        $this->assertNotEmpty($this->failuresFor('ALLUPPERCASELETTERS1'));
        $this->assertNotEmpty($this->failuresFor('NoDigitsInThisOne'));
    }

    public function test_symbols_are_optional_by_default_and_enforceable(): void
    {
        $this->assertEmpty($this->failuresFor('NoSymbolsHere123'));

        config()->set('auth.password_policy.require_symbol', true);
        $this->assertNotEmpty($this->failuresFor('NoSymbolsHere123'));
        $this->assertEmpty($this->failuresFor('WithSymbols123!'));
    }

    public function test_it_reports_every_unmet_requirement_at_once(): void
    {
        // A reader who resubmits four times to discover four rules picks a worse
        // password than one told the rules up front.
        $this->assertGreaterThan(1, count($this->failuresFor('short')));
    }

    public function test_a_non_ascii_password_satisfies_the_classes(): void
    {
        // The classes are Unicode-aware: an accented or Cyrillic password is a
        // real password, not a policy violation.
        $this->assertEmpty($this->failuresFor('Ünicodé-Pässwörd1'));
    }

    public function test_changing_a_password_rejects_one_below_policy(): void
    {
        $this->signedIn();

        $this->postJson('/api/v1/profile/password', [
            'current_password' => self::CURRENT,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'platform.validation_failed');
    }

    public function test_changing_a_password_accepts_a_compliant_one(): void
    {
        $user = $this->signedIn();

        $this->postJson('/api/v1/profile/password', [
            'current_password' => self::CURRENT,
            'password' => 'Brand-New-Passw0rd',
            'password_confirmation' => 'Brand-New-Passw0rd',
        ])->assertOk();

        $this->assertTrue(Hash::check('Brand-New-Passw0rd', $user->refresh()->password));
    }

    public function test_changing_a_password_requires_the_current_one(): void
    {
        $this->signedIn();

        // Proves the person at the keyboard is the account holder, not someone
        // who found an unlocked screen.
        $this->postJson('/api/v1/profile/password', [
            'current_password' => 'not-the-current-password',
            'password' => 'Brand-New-Passw0rd',
            'password_confirmation' => 'Brand-New-Passw0rd',
        ])->assertStatus(422);
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        $this->signedIn();

        $this->postJson('/api/v1/profile/password', [
            'current_password' => self::CURRENT,
            'password' => self::CURRENT,
            'password_confirmation' => self::CURRENT,
        ])->assertStatus(422);
    }

    public function test_the_confirmation_must_match(): void
    {
        $this->signedIn();

        $this->postJson('/api/v1/profile/password', [
            'current_password' => self::CURRENT,
            'password' => 'Brand-New-Passw0rd',
            'password_confirmation' => 'Different-Passw0rd',
        ])->assertStatus(422);
    }

    public function test_passwords_are_stored_as_a_one_way_hash(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT)]);

        $stored = $user->refresh()->password;

        $this->assertNotSame(self::CURRENT, $stored);
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check(self::CURRENT, $stored));
    }
}
