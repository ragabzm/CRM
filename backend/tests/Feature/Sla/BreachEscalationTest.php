<?php

declare(strict_types=1);

namespace Tests\Feature\Sla;

use App\Models\User;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Notifications\TicketEscalated;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The one automatic escalation condition there is.
 *
 * A missed target escalates the ticket, tells the department's supervisors,
 * and — only where a setting says so — raises the priority one step. There is
 * no rule table, no condition builder, no ordering and no per-rule switch, and
 * `NoEscalationRuleEngineTest` is what stops one appearing.
 *
 * The test that matters most is the idempotent one. This sweep runs every
 * minute; an escalation that fired each time would email a supervisor sixty
 * times an hour for as long as a ticket stayed late, and they would stop
 * reading any of them.
 */
final class BreachEscalationTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private int $departmentId;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $s = $this->app->make(SettingsRegistry::class);
        $s->set('email.enabled', false, null);
        $s->set('sla.timezone', 'Asia/Riyadh', null);
        /*
         * EVERY priority the tests use, not just `normal`.
         *
         * Two of these tests raise a `low` or an `urgent` ticket, and with
         * only the `normal` targets set they fell back to the shipped
         * defaults — two days and fourteen. Whether a ten-day-old ticket had
         * breached then depended on how many WORKING days fell in those ten,
         * which depends on what day of the week the suite happens to run. The
         * test passed for a week and failed on a Saturday.
         */
        foreach (['low', 'normal', 'high', 'urgent'] as $priority) {
            $s->set("sla.response_target_seconds.{$priority}", 3600, null);
            $s->set("sla.resolution_target_seconds.{$priority}", 7200, null);
        }

        $this->departmentId = (int) Department::firstOrCreate(
            ['name' => 'Billing'],
            ['is_active' => true],
        )->getKey();

        $this->supervisor = User::factory()->create(['department_id' => $this->departmentId]);
        $this->supervisor->syncRoles([Roles::SUPERVISOR]);

        Notification::fake();
    }

    private function breachedTicket(array $attributes = []): Ticket
    {
        $ticket = $this->makeTicket([
            'priority' => 'normal',
            'status' => 'open',
            'department_id' => $this->departmentId,
            ...$attributes,
        ]);

        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        return $ticket->refresh();
    }

    public function test_a_missed_target_escalates_the_ticket(): void
    {
        $ticket = $this->breachedTicket();

        Artisan::call('sla:sweep');

        $ticket->refresh();

        $this->assertNotNull($ticket->escalated_at);
        $this->assertStringContainsString('target was missed', (string) $ticket->escalation_reason);
    }

    public function test_it_is_attributed_to_the_system_and_names_what_decided(): void
    {
        $ticket = $this->breachedTicket();

        Artisan::call('sla:sweep');

        $event = DB::table('ticket_events')
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', 'ticket.escalated')
            ->first();

        $this->assertNotNull($event);

        /*
         * `System(sla_breach)`, not just "System". A supervisor reading
         * "System escalated this" learns nothing; the reason names the thing
         * that decided, and it appears in history exactly like a human action
         * because it went through the same command.
         */
        $this->assertSame('system', $event->actor_type);
        $this->assertSame('sla_breach', $event->actor_reason);
        $this->assertNull($event->actor_id);
    }

    public function test_the_department_supervisors_are_told_with_the_reason(): void
    {
        $this->breachedTicket();

        Artisan::call('sla:sweep');

        Notification::assertSentTo(
            $this->supervisor,
            TicketEscalated::class,
            function (TicketEscalated $notification): bool {
                $rendered = $notification->toArray($this->supervisor);

                // Enough to act on without opening the ticket first.
                return str_contains((string) $rendered['text'], 'target was missed');
            },
        );
    }

    public function test_running_the_sweep_again_escalates_nothing_and_tells_nobody_twice(): void
    {
        $ticket = $this->breachedTicket();

        Artisan::call('sla:sweep');
        $firstStamp = $ticket->refresh()->escalated_at;

        Artisan::call('sla:sweep');
        Artisan::call('sla:sweep');

        // One escalation, one history row, one notification.
        $this->assertEquals($firstStamp, $ticket->refresh()->escalated_at);
        $this->assertSame(
            1,
            DB::table('ticket_events')->where('event_type', 'ticket.escalated')->count(),
        );
        Notification::assertSentToTimes($this->supervisor, TicketEscalated::class, 1);
    }

    public function test_a_ticket_that_misses_both_targets_is_escalated_once(): void
    {
        $ticket = $this->breachedTicket();

        Artisan::call('sla:sweep');

        /*
         * Ten days late misses BOTH the response and the resolution target in
         * the same pass. Two breaches are recorded — they are two different
         * promises — and one escalation, because the ticket is either going
         * wrong or it is not.
         */
        $this->assertSame(2, DB::table('sla_events')->where('ticket_id', $ticket->getKey())->count());
        $this->assertSame(
            1,
            DB::table('ticket_events')->where('event_type', 'ticket.escalated')->count(),
        );
    }

    public function test_a_ticket_a_person_already_escalated_is_not_escalated_again(): void
    {
        $ticket = $this->breachedTicket();

        $ticket->forceFill([
            'escalated_at' => now()->subHour(),
            'escalated_by' => '7',
            'escalation_reason' => 'The customer is threatening to leave.',
        ])->save();

        Artisan::call('sla:sweep');

        // A person saw it coming. The sweep does not overwrite their reason
        // with its own, blander one.
        $this->assertSame('The customer is threatening to leave.', $ticket->refresh()->escalation_reason);
        $this->assertSame(0, DB::table('ticket_events')->where('event_type', 'ticket.escalated')->count());
    }

    public function test_priority_is_left_alone_unless_the_setting_says_otherwise(): void
    {
        $ticket = $this->breachedTicket();

        Artisan::call('sla:sweep');

        /*
         * Off by default. A sweep silently raising priority changes what every
         * agent's queue is sorted by, at 02:00, without anybody having decided
         * it should.
         */
        $this->assertSame('normal', $ticket->refresh()->priority->value);
    }

    public function test_the_setting_raises_priority_exactly_one_step(): void
    {
        $this->app->make(SettingsRegistry::class)->set('sla.breach_raises_priority', true, null);

        $ticket = $this->breachedTicket(['priority' => 'low']);

        Artisan::call('sla:sweep');

        // One step, not to the top. Low becomes Normal.
        $this->assertSame('normal', $ticket->refresh()->priority->value);
    }

    public function test_an_urgent_ticket_stays_urgent_without_an_error(): void
    {
        $this->app->make(SettingsRegistry::class)->set('sla.breach_raises_priority', true, null);

        $ticket = $this->breachedTicket(['priority' => 'urgent']);

        $exit = Artisan::call('sla:sweep');

        /*
         * Nowhere further to go, and nothing to report. Refusing would turn a
         * routine no-op into an error in a log nobody can act on, once a
         * minute, for as long as the ticket stayed late.
         */
        $this->assertSame(0, $exit);
        $this->assertSame('urgent', $ticket->refresh()->priority->value);
        $this->assertNotNull($ticket->refresh()->escalated_at);
    }

    public function test_the_priority_change_carries_no_version_and_is_recorded(): void
    {
        $this->app->make(SettingsRegistry::class)->set('sla.breach_raises_priority', true, null);

        $ticket = $this->breachedTicket(['priority' => 'normal']);

        Artisan::call('sla:sweep');

        // Through the named command, so it is in history like any other change
        // — a sweep that wrote the column directly would leave a priority
        // nobody can explain.
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->getKey(),
            'event_type' => 'ticket.priority_changed',
            'actor_type' => 'system',
        ]);
    }
}
