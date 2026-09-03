<?php

declare(strict_types=1);

namespace Tests\Feature\Seeding;

use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Platform\Attachments\Domain\ScanStatus;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Priority;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAgentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Seeding twice produces the same database as seeding once.
 *
 * This is the property the whole story rests on. A seeder that is only safe on
 * an empty database is a seeder nobody can run — because the moment you have
 * data worth keeping, running it becomes a decision instead of a command, and
 * the honest answer to "will this duplicate everything?" has to be no.
 *
 * The counts are compared table by table rather than in aggregate so a failure
 * names the seeder that broke rather than saying a number went up.
 */
final class DemoSeedersIdempotencyTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    /** Every table the demo layer touches. */
    private const TABLES = [
        'users', 'departments', 'roles', 'model_has_roles', 'ticket_categories',
        'customers', 'contact_identifiers', 'customer_notes', 'tickets',
        'ticket_messages', 'ticket_events', 'attachments', 'portal_accounts',
    ];

    public function test_running_the_seed_twice_changes_nothing(): void
    {
        $this->seedOnce();
        $first = self::counts();

        $this->seedOnce();
        $second = self::counts();

        $this->assertSame($first, $second, 'A second seed run added rows.');

        // A count that stayed the same because both runs wrote nothing would
        // satisfy the assertion above and mean the seeder is broken.
        $this->assertGreaterThanOrEqual(18, $first['tickets']);
    }

    public function test_every_table_the_demo_layer_owns_is_populated(): void
    {
        $this->seedOnce();

        foreach (self::counts() as $table => $count) {
            $this->assertGreaterThan(0, $count, "Table [{$table}] is empty after seeding.");
        }
    }

    public function test_the_published_development_accounts_are_left_alone(): void
    {
        $this->seedOnce();

        foreach (['admin@ragab.test' => 'administrator', 'super@ragab.test' => 'supervisor', 'agent@ragab.test' => 'agent'] as $email => $role) {
            $user = \App\Models\User::query()->where('email', $email)->first();

            $this->assertNotNull($user, "{$email} is missing.");
            $this->assertTrue($user->hasRole($role), "{$email} lost the {$role} role.");
        }
    }

    public function test_the_new_agents_have_a_department_and_are_active(): void
    {
        $this->seedOnce();

        foreach (DemoAgentsSeeder::AGENTS as [, $email]) {
            $user = \App\Models\User::query()->where('email', $email)->first();

            $this->assertNotNull($user, "{$email} was not seeded.");
            $this->assertNotNull($user->department_id, "{$email} has no department.");
            $this->assertTrue($user->isActive());
            $this->assertTrue($user->hasRole('agent'));
        }
    }

    public function test_the_tickets_cover_every_status_priority_and_channel(): void
    {
        $this->seedOnce();

        $this->assertGreaterThanOrEqual(18, DB::table('tickets')->count());

        foreach (TicketStatus::cases() as $status) {
            $this->assertGreaterThan(
                0,
                DB::table('tickets')->where('status', $status->value)->count(),
                "No seeded ticket is [{$status->value}].",
            );
        }

        foreach (Priority::cases() as $priority) {
            $this->assertGreaterThan(
                0,
                DB::table('tickets')->where('priority', $priority->value)->count(),
                "No seeded ticket is [{$priority->value}] priority.",
            );
        }

        foreach (TicketChannel::cases() as $channel) {
            $this->assertGreaterThan(
                0,
                DB::table('tickets')->where('channel', $channel->value)->count(),
                "No seeded ticket arrived by [{$channel->value}].",
            );
        }
    }

    public function test_the_work_is_spread_and_some_of_it_is_unclaimed(): void
    {
        $this->seedOnce();

        $agents = \App\Models\User::query()
            ->whereIn('email', array_merge(
                ['agent@ragab.test'],
                array_column(DemoAgentsSeeder::AGENTS, 1),
            ))
            ->pluck('email', 'id');

        foreach ($agents as $id => $email) {
            $this->assertGreaterThanOrEqual(
                2,
                DB::table('tickets')->where('assignee_id', $id)->count(),
                "{$email} holds fewer than two tickets, so no screen shows a workload.",
            );
        }

        $this->assertGreaterThanOrEqual(
            2,
            DB::table('tickets')->whereNull('assignee_id')->count(),
            'Nothing is unassigned, so the pool is invisible.',
        );
    }

    public function test_every_ticket_is_a_conversation_and_some_carry_a_note(): void
    {
        $this->seedOnce();

        $thin = DB::table('ticket_messages')
            ->select('ticket_id')
            ->groupBy('ticket_id')
            ->havingRaw('count(*) < 2')
            ->pluck('ticket_id');

        $this->assertCount(0, $thin, 'Some tickets have fewer than two messages.');

        $this->assertSame(
            DB::table('tickets')->count(),
            DB::table('ticket_messages')->distinct()->count('ticket_id'),
            'Some tickets have no messages at all.',
        );

        $withNotes = DB::table('ticket_messages')
            ->where('direction', MessageDirection::Internal->value)
            ->distinct()
            ->count('ticket_id');

        $this->assertGreaterThanOrEqual(3, $withNotes);
    }

    public function test_every_ticket_carries_the_history_its_lifecycle_implies(): void
    {
        $this->seedOnce();

        foreach (DB::table('tickets')->get() as $ticket) {
            $kinds = DB::table('ticket_events')
                ->where('ticket_id', $ticket->id)
                ->pluck('event_type')
                ->all();

            $this->assertContains(
                TicketEventKind::Created->value,
                $kinds,
                "Ticket {$ticket->reference} has no creation event, so its history starts mid-story.",
            );

            if ($ticket->assignee_id !== null) {
                $this->assertContains(
                    TicketEventKind::AssigneeChanged->value,
                    $kinds,
                    "Ticket {$ticket->reference} is assigned with no event saying who did it.",
                );
            }

            if ($ticket->status !== TicketStatus::Open->value) {
                $moved = array_intersect($kinds, [
                    TicketEventKind::StatusChanged->value,
                    TicketEventKind::Resolved->value,
                    TicketEventKind::Reopened->value,
                ]);

                $this->assertNotEmpty($moved, "Ticket {$ticket->reference} is [{$ticket->status}] with no event that moved it there.");
            }
        }
    }

    public function test_both_attachments_are_clean_and_actually_on_disk(): void
    {
        $this->seedOnce();

        $attachments = DB::table('attachments')->get();

        $this->assertCount(2, $attachments);

        $this->assertEqualsCanonicalizing(
            [AttachmentOwnerType::Ticket->value, AttachmentOwnerType::Customer->value],
            $attachments->pluck('owner_type')->all(),
            'The demo needs one attachment of each owner type.',
        );

        $disk = Storage::disk((string) config('attachments.disk'));

        foreach ($attachments as $attachment) {
            $this->assertSame(ScanStatus::Clean->value, $attachment->scan_status);

            $this->assertTrue(
                $disk->exists($attachment->stored_path),
                "Attachment {$attachment->filename} has a row but no file, so downloading it fails.",
            );

            $this->assertStringStartsWith(
                (string) config('attachments.prefixes.clean'),
                $attachment->stored_path,
                'A clean attachment still sitting under quarantine cannot be downloaded.',
            );
        }
    }

    public function test_the_portal_accounts_are_verified_and_linked(): void
    {
        $this->seedOnce();

        $accounts = DB::table('portal_accounts')->get();

        $this->assertCount(2, $accounts);

        foreach ($accounts as $account) {
            $this->assertNotNull($account->email_verified_at);
            $this->assertNotNull($account->customer_id, 'A demo portal account with no customer sees an empty list.');
        }
    }

    public function test_a_seeded_portal_account_can_sign_in(): void
    {
        $this->seedOnce();

        // The SPA origin, because Sanctum only attaches a session to a
        // request that presents one — the same gate a browser goes through.
        $this->setUpSpaOrigin();

        $this->withIdempotencyKey()->postJson('/api/v1/portal/auth/login', [
            'email' => 'layla.haddad@example.test',
            'password' => \Database\Seeders\DevAccountsSeeder::PASSWORD,
        ])->assertSuccessful();
    }

    public function test_the_arabic_survives_the_round_trip(): void
    {
        $this->seedOnce();

        $this->assertSame(
            'الفوترة',
            DB::table('ticket_categories')->where('name_en', 'Billing')->value('name_ar'),
        );
    }

    public function test_no_seeded_reply_claims_it_failed_to_reach_anybody(): void
    {
        $this->seedOnce();

        $failed = DB::table('ticket_messages')
            ->where('delivery_state', 'failed')
            ->count();

        /*
         * `email.enabled` is false on a fresh install by design, so a seeded
         * reply that went through the outbound path came back "failed: the
         * email channel is switched off" — and every agent reply in the demo
         * data wore a red chip saying it never reached the customer.
         *
         * Neither the send nor the failure happened. These messages are a
         * story about the past.
         */
        $this->assertSame(0, $failed, 'Seeded replies are marked as failed deliveries.');

        $this->assertSame(0, DB::table('failed_jobs')->count(), 'Seeding left failed jobs behind.');
    }

    public function test_no_settings_row_is_written(): void
    {
        $this->seedOnce();

        // Settings resolve from the registry defaults. A seeded row would
        // shadow a default for every developer and be invisible in the code.
        $this->assertSame(0, DB::table('settings')->count());
    }

    public function test_the_whole_seed_is_fast_enough_to_actually_run(): void
    {
        $started = microtime(true);
        $this->seedOnce();
        $elapsed = microtime(true) - $started;

        fwrite(STDERR, sprintf("\n  full seed: %.1fs\n", $elapsed));

        /*
         * 30s, with the story allowing up to 60. The number is not about the
         * machine — it is about whether `db:seed` stays something a developer
         * runs without thinking. Past a minute it becomes a thing you avoid,
         * and then the demo data stops being seeded at all.
         */
        $this->assertLessThan(30, $elapsed, 'The seed is slow enough that people will stop running it.');
    }

    private function seedOnce(): void
    {
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
    }

    /**
     * @return array<string, int>
     */
    private static function counts(): array
    {
        $counts = [];

        foreach (self::TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
