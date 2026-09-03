<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Notifications\TicketAssigned;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * What the bell reads, and what happens when it is clicked.
 */
final class NotificationsApiTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->make(SettingsRegistry::class)->set('email.enabled', false, null);
    }

    private function notify(User $user, int $times = 1, bool $read = false): void
    {
        for ($i = 0; $i < $times; $i++) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => TicketAssigned::class,
                'notifiable_type' => $user::class,
                'notifiable_id' => $user->getKey(),
                'data' => json_encode([
                    'ticket_id' => '01T'.$i,
                    'reference' => 'TKT-00000'.$i,
                    'text' => "Dana assigned ticket {$i} to you.",
                    'kind' => 'notifications.assigned',
                ], JSON_THROW_ON_ERROR),
                'read_at' => $read ? now() : null,
                'created_at' => now()->subMinutes($times - $i),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_it_lists_notifications_newest_first(): void
    {
        $user = $this->makeUser(Roles::AGENT);
        $this->notify($user, times: 3);

        $data = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->json('data');

        // Newest first: the bell is checked to find out what just happened.
        $this->assertSame('TKT-000002', $data[0]['reference']);
    }

    public function test_it_mingles_read_and_unread(): void
    {
        $user = $this->makeUser(Roles::AGENT);
        $this->notify($user, times: 2, read: true);
        $this->notify($user, times: 1);

        $data = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->json('data');

        /*
         * One list, not two tabs. Somebody checking the bell wants to know what
         * happened; splitting that into "new" and "everything" makes them look
         * in two places for one answer.
         */
        $this->assertCount(3, $data);
        $this->assertContains(true, array_column($data, 'read'));
        $this->assertContains(false, array_column($data, 'read'));
    }

    public function test_it_counts_the_unread_separately_from_the_list(): void
    {
        $user = $this->makeUser(Roles::AGENT);
        $this->notify($user, times: 30);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk();

        /*
         * The list is capped at twenty; a badge reading "20" when there were
         * thirty would be worse than no badge, because it would look precise.
         */
        $this->assertCount(20, $response->json('data'));
        $this->assertSame(30, $response->json('unread_count'));
    }

    public function test_it_carries_the_ticket_so_the_bell_can_navigate(): void
    {
        $user = $this->makeUser(Roles::AGENT);
        $this->notify($user);

        $item = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->json('data.0');

        // A notification with no way to act on it makes somebody go and find
        // the ticket by hand.
        $this->assertSame('01T0', $item['ticket_id']);
    }

    public function test_a_person_sees_only_their_own(): void
    {
        $mine = $this->makeUser(Roles::AGENT);
        $theirs = $this->makeUser(Roles::AGENT);

        $this->notify($theirs, times: 5);

        $response = $this->actingAs($mine)->getJson('/api/v1/notifications')->assertOk();

        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('unread_count'));
    }

    public function test_marking_one_read_drops_the_count(): void
    {
        $user = $this->makeUser(Roles::AGENT);
        $this->notify($user, times: 2);

        $id = DB::table('notifications')->value('id');

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk();

        $this->assertSame(1, $this->actingAs($user)->getJson('/api/v1/notifications')->json('unread_count'));
    }

    public function test_marking_one_read_twice_is_not_an_error(): void
    {
        $user = $this->makeUser(Roles::AGENT);
        $this->notify($user);

        $id = DB::table('notifications')->value('id');

        foreach ([1, 2] as $_) {
            $this->actingAs($user)
                ->withHeader('Idempotency-Key', (string) Str::ulid())
                ->postJson("/api/v1/notifications/{$id}/read")
                ->assertOk();
        }

        // A second click on an already-read item is not an error, and treating
        // it as one would surface a red banner for nothing.
        $this->assertSame(0, $this->actingAs($user)->getJson('/api/v1/notifications')->json('unread_count'));
    }

    public function test_somebody_elses_notification_is_not_found(): void
    {
        $mine = $this->makeUser(Roles::AGENT);
        $theirs = $this->makeUser(Roles::AGENT);

        $this->notify($theirs);
        $id = DB::table('notifications')->value('id');

        /*
         * 404, not 403. A 403 would confirm the id exists, and these ids are
         * the only thing between a curious agent and knowing how many
         * notifications a colleague has.
         */
        $this->actingAs($mine)
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson("/api/v1/notifications/{$id}/read")
            ->assertStatus(404)
            ->assertJsonPath('code', 'notifications.not_found');

        $this->assertNull(DB::table('notifications')->value('read_at'));
    }

    public function test_an_unauthenticated_caller_is_refused(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }

    public function test_the_unread_index_exists(): void
    {
        /*
         * The bell asks "how many unread for me?" on every page load. Without
         * this index that is a scan of every notification ever sent to anybody.
         */
        $indexes = collect(DB::select("select name from sqlite_master where type='index' and tbl_name='notifications'"))
            ->pluck('name');

        $this->assertContains('notifications_recipient_unread_idx', $indexes);
    }
}
