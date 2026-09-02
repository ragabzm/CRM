<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Admin;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class SettingsApiTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());

        return $user;
    }

    public function test_an_administrator_reads_every_setting_with_its_rule(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $response = $this->getJson('/api/v1/admin/settings')->assertOk();

        $keys = array_column($response->json('data'), 'key');

        $this->assertContains('tickets.auto_close_hours', $keys);
        $this->assertContains('sla.at_risk_threshold_percent', $keys);
        $this->assertContains('email.mailbox.host', $keys);

        $row = collect($response->json('data'))->firstWhere('key', 'tickets.auto_close_hours');

        // The console needs the default to offer "reset", the summary to
        // explain the setting, and the type to pick an input — all in one trip.
        $this->assertSame('int', $row['type']);
        $this->assertSame(168, $row['default']);
        $this->assertNotSame('', $row['summary']);
        $this->assertFalse($row['secret']);
    }

    public function test_an_enum_setting_publishes_its_allowed_values(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $row = collect($this->getJson('/api/v1/admin/settings')->json('data'))
            ->firstWhere('key', 'platform.default_locale');

        // So the console renders a select rather than a free-text box that can
        // be filled with a locale the application does not have.
        $this->assertSame('enum', $row['type']);
        $this->assertSame(['en', 'ar'], $row['allowed_values']);
    }

    public function test_a_secret_is_never_sent_to_the_browser(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/email.mailbox.password', ['value' => 'hunter2'])
            ->assertOk()
            ->assertJsonPath('value', '••••••••');

        $body = $this->getJson('/api/v1/admin/settings')->getContent();

        // Not in the row, and not anywhere else in the payload either.
        $this->assertStringNotContainsString('hunter2', (string) $body);
    }

    public function test_a_valid_write_takes_effect(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/tickets.auto_close_hours', ['value' => 24])
            ->assertOk()
            ->assertJsonPath('value', 24);

        $this->getJson('/api/v1/admin/settings')
            ->assertJsonPath('data.'.$this->indexOfKey('tickets.auto_close_hours').'.value', 24);
    }

    private function indexOfKey(string $key): int
    {
        $keys = array_column($this->getJson('/api/v1/admin/settings')->json('data'), 'key');
        $index = array_search($key, $keys, true);

        $this->assertIsInt($index);

        return $index;
    }

    public function test_a_rejected_write_names_the_rule_it_broke(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/tickets.auto_close_hours', ['value' => 0])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'platform.setting_invalid')
            // RFC 9457 extension members sit at the top level of the document.
            ->assertJsonPath('setting', 'tickets.auto_close_hours');

        // Not "invalid value" — the administrator is told the actual bound.
        $detail = (string) $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/tickets.auto_close_hours', ['value' => 0])
            ->json('detail');

        $this->assertStringContainsString('between 1 hour', $detail);
    }

    public function test_a_write_to_an_unknown_key_is_a_404_not_a_new_row(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/made.up.key', ['value' => 1])
            ->assertStatus(404)
            ->assertJsonPath('code', 'platform.setting_unknown');

        $this->assertDatabaseMissing('settings', ['key' => 'made.up.key']);
    }

    public function test_a_write_without_a_value_is_refused(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/tickets.auto_close_hours', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'platform.setting_invalid');
    }

    public function test_a_supervisor_cannot_read_or_write_settings(): void
    {
        $this->actingAsRole(Roles::SUPERVISOR);

        // Configuration is an administrator concern. A supervisor who could
        // change SLA targets could rewrite the numbers they are measured on.
        $this->getJson('/api/v1/admin/settings')->assertStatus(403);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/tickets.auto_close_hours', ['value' => 24])
            ->assertStatus(403);

        $this->assertDatabaseMissing('settings', ['key' => 'tickets.auto_close_hours']);
    }

    public function test_an_agent_cannot_reach_the_console(): void
    {
        $this->actingAsRole(Roles::AGENT);

        $this->getJson('/api/v1/admin/settings')->assertStatus(403);
    }

    public function test_a_guest_cannot_reach_the_console(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertStatus(401);
    }

    public function test_a_write_records_who_changed_it(): void
    {
        $administrator = $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/tickets.auto_close_hours', ['value' => 24])
            ->assertOk();

        // Story 2.4 turns this column into a full audit trail; the attribution
        // has to be captured at write time or it is gone.
        $this->assertDatabaseHas('settings', [
            'key' => 'tickets.auto_close_hours',
            'updated_by' => $administrator->getKey(),
        ]);
    }
}
