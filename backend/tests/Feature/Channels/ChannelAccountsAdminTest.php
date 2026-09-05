<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Models\User;
use App\Modules\Channels\Adapters\WebFormChannelAdapter;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * An administrator turns a channel off, and the right things stop.
 *
 * The distinction the story draws is the whole test: NEW inbound stops, and
 * everything already raised through that channel stays exactly as workable as
 * it was. A disable that also froze a hundred open tickets would be a disable
 * nobody dares use.
 */
final class ChannelAccountsAdminTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private string $accountId;

    private int $departmentId;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->departmentId = (int) DB::table('departments')->insertGetId([
            'name' => 'Support',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->categoryId = (int) DB::table('ticket_categories')->insertGetId([
            'name_en' => 'Billing',
            'name_ar' => 'الفوترة',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->accountId = (string) Str::ulid();

        DB::table('channel_accounts')->insert([
            'id' => $this->accountId,
            'channel' => WebFormChannelAdapter::CHANNEL,
            'name' => 'Public form',
            'is_active' => true,
            'department_id' => $this->departmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hana Yousef',
            'contact' => 'hana@example.test',
            'subject' => 'My invoice is wrong',
            'category_id' => $this->categoryId,
            'message' => 'I was charged twice for March.',
            'hp_company' => '',
            'rendered_at' => now()->subSeconds(30)->toIso8601String(),
        ], $overrides);
    }

    public function test_an_administrator_sees_every_account_with_its_department_and_traffic(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->postJson('/api/v1/inbound/web-form', $this->submission())->assertCreated();

        $response = $this->getJson('/api/v1/admin/channels');

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $this->accountId);

        $this->assertSame('web_form', $row['channel']);
        $this->assertSame('Public form', $row['name']);
        $this->assertTrue($row['is_active']);
        $this->assertSame('Support', $row['department_name']);
        // The number an administrator scans the list for: is anything arriving?
        $this->assertSame(1, $row['inbound_last_7_days']);
    }

    public function test_an_agent_cannot_reach_the_channel_list(): void
    {
        $this->actingAsRole(Roles::AGENT);

        $this->getJson('/api/v1/admin/channels')->assertForbidden();
        $this->withIdempotencyKey()->patchJson("/api/v1/admin/channels/{$this->accountId}", ['is_active' => false])
            ->assertForbidden();
    }

    public function test_disabling_a_channel_stops_new_inbound(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->withIdempotencyKey()->patchJson("/api/v1/admin/channels/{$this->accountId}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->postJson('/api/v1/inbound/web-form', $this->submission())
            ->assertStatus(403)
            ->assertJsonPath('code', 'channels.channel_disabled');

        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, DB::table('inbound_messages')->count());
    }

    public function test_tickets_already_raised_on_a_disabled_channel_stay_workable(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $this->postJson('/api/v1/inbound/web-form', $this->submission())->assertCreated();
        $ticket = Ticket::query()->sole();

        $this->withIdempotencyKey()->patchJson("/api/v1/admin/channels/{$this->accountId}", ['is_active' => false])->assertOk();

        /*
         * Still there, still open, still readable. Disabling a channel is a
         * decision about the FRONT DOOR; the people already inside are not
         * asked to leave.
         */
        $this->getJson('/api/v1/tickets/'.$ticket->getKey())
            ->assertOk()
            ->assertJsonPath('status', 'open');
    }

    public function test_the_bound_department_can_be_changed(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $billing = (int) DB::table('departments')->insertGetId([
            'name' => 'Billing',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withIdempotencyKey()->patchJson("/api/v1/admin/channels/{$this->accountId}", ['department_id' => $billing])
            ->assertOk()
            ->assertJsonPath('department_id', $billing);

        $this->postJson('/api/v1/inbound/web-form', $this->submission())->assertCreated();

        $this->assertSame($billing, (int) Ticket::query()->sole()->department_id);
    }
}
