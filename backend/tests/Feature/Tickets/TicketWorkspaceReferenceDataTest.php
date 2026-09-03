<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * The selects on the ticket workspace have something to select.
 *
 * `TicketDetailPage` fetches `/ticket-categories` and `/assignees` on mount
 * and swallows a failure on purpose — reference data that will not load is a
 * smaller problem than a workspace that will not open. Neither endpoint
 * existed. Both 404ed on every page load, silently, and the Category and
 * Assignee selects have been empty since the workspace shipped: an agent could
 * not see who held a ticket, and could not move it.
 *
 * The swallow is right. What was missing is anything that noticed the options
 * were always empty — which is what this file is.
 */
final class TicketWorkspaceReferenceDataTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->setUpSpaOrigin();
    }

    public function test_an_agent_can_read_the_category_list_without_managing_it(): void
    {
        $body = $this->actingAs(self::agent())
            ->getJson('/api/v1/ticket-categories')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($body);
        $this->assertSame(['id', 'name'], array_keys($body[0]));

        // The one thing the select needs and could never get: an agent does
        // not hold `setting.manage`, so `/admin/categories` is closed to them.
        $this->actingAs(self::agent())
            ->getJson('/api/v1/admin/categories')
            ->assertForbidden();
    }

    public function test_the_category_name_is_in_the_readers_language(): void
    {
        $reader = self::agent();
        $reader->forceFill(['preferred_locale' => 'ar'])->save();

        $names = array_column(
            $this->actingAs($reader)->getJson('/api/v1/ticket-categories')->json('data'),
            'name',
        );

        $this->assertContains('الفوترة', $names);
    }

    public function test_an_agent_can_read_who_a_ticket_can_go_to(): void
    {
        $body = $this->actingAs(self::agent())
            ->getJson('/api/v1/assignees')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($body);
        $this->assertSame(['id', 'name'], array_keys($body[0]));

        $names = array_column($body, 'name');
        $this->assertSame($names, array_values(array_unique($names)));
    }

    public function test_a_deactivated_colleague_is_not_offered(): void
    {
        $leaver = User::query()->where('email', 'agent.sales@ragab.test')->firstOrFail();
        $leaver->forceFill(['is_active' => false])->save();

        $ids = array_column(
            $this->actingAs(self::agent())->getJson('/api/v1/assignees')->json('data'),
            'id',
        );

        /*
         * `AssignTicket` refuses a deactivated assignee, so offering one could
         * only ever produce an error — and in the meantime it invites an agent
         * to park work where nobody is looking.
         */
        $this->assertNotContains((int) $leaver->getKey(), $ids);
    }

    public function test_neither_list_is_open_to_somebody_with_no_ticket_access(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->getJson('/api/v1/ticket-categories')->assertForbidden();
        $this->actingAs($outsider)->getJson('/api/v1/assignees')->assertForbidden();
    }

    private static function agent(): User
    {
        return User::query()->where('email', 'agent@ragab.test')->firstOrFail();
    }
}
