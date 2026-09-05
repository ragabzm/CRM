<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Personal\Reminder;
use App\Modules\Tickets\Domain\Personal\ReminderSweep;
use App\Modules\Tickets\Domain\Personal\Task;
use App\Modules\Tickets\Notifications\ReminderDue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * "Come back to this at nine tomorrow."
 *
 * Two rules carry the whole feature and both are about NOT sending things. A
 * reminder set for a moment that has gone is refused rather than fired at
 * once; a reminder that has fired never fires again, however many times the
 * sweep runs. A reminder arriving twice is worse than one arriving late — the
 * second one makes the person check whether they missed something.
 */
final class RemindersTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->agent = $this->makeUser(Roles::AGENT);
    }

    private function asAgent(): self
    {
        $this->actingAs($this->agent, 'web');

        return $this;
    }

    public function test_a_reminder_is_set_on_a_ticket_for_a_future_moment(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/reminders', [
            'ticket_id' => (string) $ticket->getKey(),
            'remind_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertSame(1, Reminder::query()->whereNull('fired_at')->count());
    }

    public function test_a_reminder_can_be_set_on_a_task_instead(): void
    {
        $task = $this->task();

        $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/reminders', [
            'task_id' => (string) $task->getKey(),
            'remind_at' => now()->addHours(3)->toIso8601String(),
        ])->assertCreated();

        $this->assertSame((string) $task->getKey(), (string) Reminder::query()->value('task_id'));
    }

    public function test_a_reminder_in_the_past_is_refused_with_the_reason(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/reminders', [
            'ticket_id' => (string) $ticket->getKey(),
            'remind_at' => now()->subHour()->toIso8601String(),
        ]);

        $response->assertStatus(422);
        $this->assertSame('tickets.reminder_in_the_past', $response->json('code'));
        $this->assertNotEmpty($response->json('detail'));

        /*
         * Neither accepted-and-fired-immediately nor silently moved to now.
         * The first turns a mistyped year into a notification about nothing;
         * the second hides the mistake so the agent never learns of it.
         */
        $this->assertSame(0, Reminder::query()->count());
    }

    public function test_the_past_date_refusal_lives_in_the_command_not_the_picker(): void
    {
        $ticket = $this->makeTicket();

        // Straight at the command, the way a script or a console would reach
        // it. The API is reachable directly and so is this.
        $this->expectException(\App\Modules\Platform\Exceptions\ProblemException::class);

        $this->app->make(\App\Modules\Tickets\Domain\Personal\Commands\CreateReminder::class)->handle(
            (int) $this->agent->getKey(),
            \Carbon\CarbonImmutable::now()->subMinute(),
            (string) $ticket->getKey(),
        );
    }

    public function test_a_reminder_about_both_a_ticket_and_a_task_is_refused(): void
    {
        $ticket = $this->makeTicket();
        $task = $this->task();

        $response = $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/reminders', [
            'ticket_id' => (string) $ticket->getKey(),
            'task_id' => (string) $task->getKey(),
            'remind_at' => now()->addDay()->toIso8601String(),
        ]);

        // It would render twice on the Home tab and name something the owner
        // cannot identify.
        $response->assertStatus(422);
        $this->assertSame('tickets.reminder_target_required', $response->json('code'));
    }

    public function test_a_reminder_about_nothing_is_refused(): void
    {
        $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/reminders', [
            'remind_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_the_database_refuses_a_reminder_with_two_targets_or_none(): void
    {
        /*
         * The command is one door; a migration, a seeder or a console script
         * is another. Proven against the real constraint rather than the
         * command that usually gets there first.
         */
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The CHECK constraint is only created on PostgreSQL.');
        }

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('reminders')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => (int) $this->agent->getKey(),
            'ticket_id' => null,
            'task_id' => null,
            'remind_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_due_reminder_notifies_its_owner(): void
    {
        Notification::fake();

        $ticket = $this->makeTicket();
        $this->reminder(['ticket_id' => (string) $ticket->getKey(), 'remind_at' => now()->subMinute()]);

        $fired = $this->app->make(ReminderSweep::class)->run();

        $this->assertSame(1, $fired);
        Notification::assertSentTo($this->agent, ReminderDue::class);
    }

    public function test_a_due_reminder_fires_once_however_often_the_sweep_runs(): void
    {
        Notification::fake();

        $ticket = $this->makeTicket();
        $this->reminder(['ticket_id' => (string) $ticket->getKey(), 'remind_at' => now()->subMinute()]);

        $sweep = $this->app->make(ReminderSweep::class);

        $this->assertSame(1, $sweep->run());
        // A slow minute, two workers, a retried job. The second run selects
        // nothing because `fired_at` was written in the same transaction.
        $this->assertSame(0, $sweep->run());
        $this->assertSame(0, $sweep->run());

        Notification::assertSentToTimes($this->agent, ReminderDue::class, 1);
    }

    public function test_the_console_command_is_the_same_sweep(): void
    {
        Notification::fake();

        $ticket = $this->makeTicket();
        $this->reminder(['ticket_id' => (string) $ticket->getKey(), 'remind_at' => now()->subMinute()]);

        $this->artisan('reminders:sweep')->assertSuccessful();
        $this->artisan('reminders:sweep')->assertSuccessful();

        Notification::assertSentToTimes($this->agent, ReminderDue::class, 1);
    }

    public function test_a_reminder_that_is_not_due_yet_is_left_alone(): void
    {
        Notification::fake();

        $ticket = $this->makeTicket();
        $this->reminder(['ticket_id' => (string) $ticket->getKey(), 'remind_at' => now()->addHour()]);

        $this->assertSame(0, $this->app->make(ReminderSweep::class)->run());
        $this->assertNull(Reminder::query()->value('fired_at'));
        Notification::assertNothingSent();
    }

    public function test_a_deactivated_owner_is_not_notified_but_the_row_stops_being_due(): void
    {
        Notification::fake();

        $ticket = $this->makeTicket();
        $this->reminder(['ticket_id' => (string) $ticket->getKey(), 'remind_at' => now()->subMinute()]);

        $this->agent->forceFill(['is_active' => false])->save();

        $this->app->make(ReminderSweep::class)->run();

        Notification::assertNothingSent();
        /*
         * Still marked fired. A row that is skipped without being marked sits
         * in the sweep for ever, retried every minute of every day.
         */
        $this->assertNotNull(Reminder::query()->value('fired_at'));
    }

    public function test_a_reminder_arrives_in_the_owners_language(): void
    {
        $this->agent->forceFill(['preferred_locale' => 'ar'])->save();

        $ticket = $this->makeTicket(['subject' => 'A subject']);
        $reminder = $this->reminder([
            'ticket_id' => (string) $ticket->getKey(),
            'remind_at' => now()->subMinute(),
        ]);

        $notification = new ReminderDue(
            (string) $reminder->getKey(),
            'A subject',
            (string) $ticket->getKey(),
            (string) $ticket->reference,
        );

        $stored = $notification->toArray($this->agent->refresh());

        // The RECIPIENT's language, not the sender's — and stored that way,
        // because it is a record of something that was said to this person.
        $this->assertSame(__('notifications.reminder.line', ['about' => 'A subject'], 'ar'), $stored['text']);
    }

    public function test_a_reminder_on_a_standalone_task_still_notifies(): void
    {
        Notification::fake();

        $task = $this->task('Call the courier');
        $this->reminder(['task_id' => (string) $task->getKey(), 'remind_at' => now()->subMinute()]);

        $this->app->make(ReminderSweep::class)->run();

        Notification::assertSentTo(
            $this->agent,
            ReminderDue::class,
            static fn (ReminderDue $n): bool => $n->about === 'Call the courier' && $n->ticketId === null,
        );
    }

    public function test_a_reminder_on_somebody_elses_task_is_not_found(): void
    {
        $colleague = $this->makeUser(Roles::AGENT);

        $theirs = new Task;
        $theirs->forceFill(['user_id' => (int) $colleague->getKey(), 'title' => 'Theirs'])->save();

        $this->asAgent()->withIdempotencyKey()->postJson('/api/v1/me/reminders', [
            'task_id' => (string) $theirs->getKey(),
            'remind_at' => now()->addDay()->toIso8601String(),
        ])->assertNotFound();
    }

    public function test_there_is_no_snooze_and_no_repeat(): void
    {
        $this->reminder(['task_id' => (string) $this->task()->getKey(), 'remind_at' => now()->addDay()]);

        $columns = array_keys((array) DB::table('reminders')->first());

        /*
         * A reminder that can be pushed is a reminder nobody acts on, and a
         * repeating one is a scheduler. Both are named in the story as not in
         * this version.
         */
        foreach (['snoozed_until', 'snooze_count', 'recurrence', 'repeat_every'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }

    private function task(string $title = 'A task'): Task
    {
        $task = new Task;
        $task->forceFill(['user_id' => (int) $this->agent->getKey(), 'title' => $title])->save();

        return $task;
    }

    /** @param array<string, mixed> $attributes */
    private function reminder(array $attributes): Reminder
    {
        $reminder = new Reminder;

        $reminder->forceFill([
            'user_id' => (int) $this->agent->getKey(),
            'ticket_id' => null,
            'task_id' => null,
            'fired_at' => null,
            ...$attributes,
        ])->save();

        return $reminder;
    }
}
