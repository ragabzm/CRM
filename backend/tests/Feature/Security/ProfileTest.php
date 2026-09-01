<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
    }

    private function signedIn(array $attributes = []): User
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD), ...$attributes]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        return $user;
    }

    public function test_a_staff_member_reads_their_own_profile(): void
    {
        $user = $this->signedIn(['name' => 'Hana Yousef']);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJson([
                'id' => $user->id,
                'name' => 'Hana Yousef',
                'email' => $user->email,
                'preferred_locale' => 'en',
            ]);
    }

    public function test_the_profile_requires_a_session(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
        $this->patchJson('/api/v1/profile', ['name' => 'X'])->assertStatus(401);
    }

    public function test_a_staff_member_edits_their_name(): void
    {
        $user = $this->signedIn(['name' => 'Old Name']);

        $this->patchJson('/api/v1/profile', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');

        $this->assertSame('New Name', $user->refresh()->name);
    }

    public function test_a_staff_member_chooses_their_language(): void
    {
        $user = $this->signedIn();

        $this->patchJson('/api/v1/profile', ['preferred_locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('preferred_locale', 'ar');

        // Stored on the user, not only in a cookie, so it survives a new device.
        $this->assertSame('ar', $user->refresh()->preferred_locale);
        $this->getJson('/api/v1/profile')->assertJsonPath('preferred_locale', 'ar');
    }

    public function test_an_unsupported_language_is_refused(): void
    {
        $this->signedIn();

        $this->patchJson('/api/v1/profile', ['preferred_locale' => 'fr'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'platform.validation_failed');
    }

    public function test_an_over_long_name_is_refused(): void
    {
        $this->signedIn();

        $this->patchJson('/api/v1/profile', ['name' => str_repeat('a', 121)])
            ->assertStatus(422);
    }

    public function test_a_partial_update_leaves_other_fields_alone(): void
    {
        $user = $this->signedIn(['name' => 'Hana Yousef']);
        $this->patchJson('/api/v1/profile', ['preferred_locale' => 'ar'])->assertOk();

        $this->assertSame('Hana Yousef', $user->refresh()->name);
    }

    public function test_the_email_cannot_be_changed_here(): void
    {
        $user = $this->signedIn();
        $original = $user->email;

        $this->patchJson('/api/v1/profile', ['email' => 'attacker@evil.test'])->assertOk();

        // Changing the sign-in address is an identity change needing
        // verification of the new address and notice to the old — its own story,
        // not a field on this form.
        $this->assertSame($original, $user->refresh()->email);
    }

    public function test_a_staff_member_cannot_edit_another_persons_profile(): void
    {
        $other = User::factory()->create(['name' => 'Someone Else']);
        $this->signedIn();

        // There is no id in the route, so there is no object to fail to
        // authorise — the endpoint is scoped to the session by construction.
        $this->patchJson('/api/v1/profile', ['name' => 'Hijacked'])->assertOk();

        $this->assertSame('Someone Else', $other->refresh()->name);
    }

    public function test_the_locale_defaults_to_english_when_unset(): void
    {
        $user = $this->signedIn();
        $user->forceFill(['preferred_locale' => null])->save();

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('preferred_locale', 'en');
    }
}
