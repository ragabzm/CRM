<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API answers in the language the reader is reading.
 *
 * Nothing set the application locale per request, so `app()->getLocale()` was
 * always the config default and every string the SERVER chose the wording of
 * came out English. Notifications escaped it — `HasLocalePreference` makes
 * Laravel wrap each send — and nothing else did.
 *
 * The visible symptom was a ticket list in Arabic with Arabic headers, Arabic
 * statuses, and an English category name in the next column.
 */
final class RequestLocaleTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->agent = User::factory()->create(['preferred_locale' => 'en']);
        $this->agent->assignRole(Roles::AGENT);
    }

    public function test_the_header_decides(): void
    {
        $this->actingAs($this->agent)
            ->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/tickets')
            ->assertOk();

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_a_full_language_tag_is_understood(): void
    {
        // Browsers send `ar-EG,ar;q=0.9,en;q=0.8`, not `ar`.
        $this->actingAs($this->agent)
            ->withHeader('Accept-Language', 'ar-EG,ar;q=0.9,en;q=0.8')
            ->getJson('/api/v1/tickets')
            ->assertOk();

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_the_account_preference_answers_when_no_header_arrives(): void
    {
        $this->agent->forceFill(['preferred_locale' => 'ar'])->save();

        /*
         * Cleared deliberately. Laravel's test client sends
         * `Accept-Language: en-us,en;q=0.5` of its own accord, and so does
         * every browser — which is why this fallback is for the callers that
         * are neither: a webhook replay, a console command hitting an internal
         * route, an integration posting JSON.
         */
        $this->actingAs($this->agent)
            ->withHeader('Accept-Language', '')
            ->getJson('/api/v1/tickets')
            ->assertOk();

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_the_header_beats_the_stored_preference(): void
    {
        $this->agent->forceFill(['preferred_locale' => 'ar'])->save();

        $this->actingAs($this->agent)
            ->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/tickets')
            ->assertOk();

        /*
         * Somebody who switches the interface for one conversation has not
         * changed their account — they have changed what they want to read,
         * and only for now.
         */
        $this->assertSame('en', app()->getLocale());
    }

    public function test_a_language_we_do_not_have_is_ignored_rather_than_obeyed(): void
    {
        $this->actingAs($this->agent)
            ->withHeader('Accept-Language', '../../etc/passwd')
            ->getJson('/api/v1/tickets')
            ->assertOk();

        /*
         * `Accept-Language` is caller-supplied text. Handing it to `setLocale`
         * unchecked would let a request name a translation file that does not
         * exist — or a path.
         */
        $this->assertSame(config('app.locale'), app()->getLocale());
    }
}
