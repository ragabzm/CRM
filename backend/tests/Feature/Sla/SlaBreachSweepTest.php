<?php

declare(strict_types=1);

namespace Tests\Feature\Sla;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Breaches get written down once, by something that runs on its own.
 *
 * Waiting for somebody to open a ticket would mean a target missed at 02:00 on
 * a quiet ticket nobody looks at is never recorded — and those are exactly the
 * ones worth knowing about.
 */
final class SlaBreachSweepTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $s = $this->app->make(SettingsRegistry::class);
        $s->set('email.enabled', false, null);
        $s->set('sla.timezone', 'Asia/Riyadh', null);
        $s->set('sla.response_target_seconds.normal', 3600, null);
        $s->set('sla.resolution_target_seconds.normal', 7200, null);
    }

    /** A ticket raised far enough in the past that both targets have passed. */
    private function breachedTicket(): Ticket
    {
        $ticket = $this->makeTicket(['priority' => 'normal', 'status' => 'open']);

        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        return $ticket->refresh();
    }

    public function test_it_records_a_breach(): void
    {
        $ticket = $this->breachedTicket();

        Artisan::call('sla:sweep');

        $this->assertSame(
            2,
            DB::table('sla_events')->where('ticket_id', $ticket->getKey())->count(),
            'Both the response and the resolution target were missed.',
        );
    }

    public function test_a_second_sweep_records_nothing_new(): void
    {
        $this->breachedTicket();

        Artisan::call('sla:sweep');
        Artisan::call('sla:sweep');
        Artisan::call('sla:sweep');

        /*
         * The sweep runs every minute and will see the same breach sixty times
         * an hour. A unique index on (ticket_id, target) makes the repeats
         * lose — deliberately not a "have I seen this?" check, which two
         * overlapping sweeps would both pass.
         */
        $this->assertSame(2, DB::table('sla_events')->count());
    }

    public function test_it_records_what_the_ticket_was_worth_at_the_time(): void
    {
        $ticket = $this->breachedTicket();

        Artisan::call('sla:sweep');

        $event = DB::table('sla_events')->where('target', 'response')->first();

        $this->assertSame('normal', $event->priority);
        $this->assertSame(60, (int) $event->target_minutes);
        $this->assertGreaterThan(60, (int) $event->elapsed_minutes);

        // Downgrading the ticket afterwards must not retroactively turn a
        // missed P1 target into a missed P4 one.
        $ticket->forceFill(['priority' => 'low'])->save();
        Artisan::call('sla:sweep');

        $this->assertSame('normal', DB::table('sla_events')->where('target', 'response')->value('priority'));
    }

    public function test_a_breach_survives_the_ticket_being_resolved(): void
    {
        $ticket = $this->breachedTicket();

        Artisan::call('sla:sweep');
        $ticket->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();
        Artisan::call('sla:sweep');

        /*
         * A breach is an event, not a state. Recomputing it later would give a
         * different answer every time a target or the schedule changed, and
         * "how many did we miss last quarter" would stop being answerable.
         */
        $this->assertSame(2, DB::table('sla_events')->where('ticket_id', $ticket->getKey())->count());
    }

    public function test_a_ticket_inside_its_target_records_nothing(): void
    {
        $this->makeTicket(['priority' => 'normal', 'status' => 'open']);

        Artisan::call('sla:sweep');

        $this->assertSame(0, DB::table('sla_events')->count());
    }

    public function test_a_resolved_ticket_is_not_examined(): void
    {
        $ticket = $this->breachedTicket();
        $ticket->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();

        Artisan::call('sla:sweep');

        /*
         * Its outcome was decided when it was resolved. Re-examining every
         * closed ticket every minute forever would make the sweep grow without
         * bound.
         */
        $this->assertSame(0, DB::table('sla_events')->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->breachedTicket();

        Artisan::call('sla:sweep', ['--dry-run' => true]);

        $this->assertSame(0, DB::table('sla_events')->count());
    }

    public function test_nothing_breaches_while_the_desk_is_shut(): void
    {
        $this->app->make(SettingsRegistry::class)->set(
            'sla.working_hours',
            array_fill_keys(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'], null),
            null,
        );

        $this->breachedTicket();

        Artisan::call('sla:sweep');

        // "We are shut this week" is a legitimate thing to configure, and
        // nothing should breach while it is true.
        $this->assertSame(0, DB::table('sla_events')->count());
    }
}
