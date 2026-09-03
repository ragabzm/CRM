<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Notifications\SlaWarning;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Who hears that a target is slipping, and who hears that it is gone.
 *
 * The escalation is the point: an at-risk ticket is the assignee's to save,
 * while a missed target is the team's problem — and somebody has to be able to
 * reassign it.
 */
final class SlaNotificationsTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private int $departmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $s = $this->app->make(SettingsRegistry::class);
        $s->set('email.enabled', false, null);
        $s->set('sla.timezone', 'Asia/Riyadh', null);
        $s->set('sla.response_target_seconds.normal', 3600, null);
        $s->set('sla.resolution_target_seconds.normal', 7200, null);

        $this->departmentId = $this->makeDepartment('Billing');
    }

    private function inDepartment(string $role): User
    {
        $user = $this->makeUser($role);
        $user->forceFill(['department_id' => $this->departmentId])->save();

        return $user->refresh();
    }

    public function test_a_breach_tells_the_assignee(): void
    {
        Notification::fake();

        $agent = $this->inDepartment(Roles::AGENT);
        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey(), 'department_id' => $this->departmentId]);
        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        Artisan::call('sla:sweep');

        Notification::assertSentTo($agent, SlaWarning::class);
    }

    public function test_a_breach_escalates_to_the_departments_supervisors(): void
    {
        Notification::fake();

        $agent = $this->inDepartment(Roles::AGENT);
        $supervisor = $this->inDepartment(Roles::SUPERVISOR);

        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey(), 'department_id' => $this->departmentId]);
        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        Artisan::call('sla:sweep');

        // A missed target is the team's problem, and somebody has to be able to
        // reassign it.
        Notification::assertSentTo($supervisor, SlaWarning::class);
    }

    public function test_a_supervisor_in_another_department_hears_nothing(): void
    {
        Notification::fake();

        $agent = $this->inDepartment(Roles::AGENT);
        $elsewhere = $this->makeUser(Roles::SUPERVISOR);
        $elsewhere->forceFill(['department_id' => $this->makeDepartment('Support')])->save();

        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey(), 'department_id' => $this->departmentId]);
        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        Artisan::call('sla:sweep');

        Notification::assertNotSentTo($elsewhere->refresh(), SlaWarning::class);
    }

    public function test_a_ticket_with_no_department_escalates_to_nobody(): void
    {
        Notification::fake();

        $agent = $this->inDepartment(Roles::AGENT);
        $supervisor = $this->inDepartment(Roles::SUPERVISOR);

        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey(), 'department_id' => null]);
        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        Artisan::call('sla:sweep');

        /*
         * Falling back to "every supervisor" would turn one late ticket into an
         * email to the whole management layer.
         */
        Notification::assertNotSentTo($supervisor, SlaWarning::class);
        Notification::assertSentTo($agent, SlaWarning::class);
    }

    public function test_a_breach_is_announced_once_however_often_the_sweep_runs(): void
    {
        Notification::fake();

        $agent = $this->inDepartment(Roles::AGENT);
        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey(), 'department_id' => $this->departmentId]);
        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        Artisan::call('sla:sweep');
        Artisan::call('sla:sweep');
        Artisan::call('sla:sweep');

        /*
         * The sweep runs every minute. Without the unique index gating the
         * announcement, the assignee would be emailed once a minute for as long
         * as the ticket stayed late.
         *
         * Two: the response target and the resolution target, each once.
         */
        Notification::assertSentToTimes($agent, SlaWarning::class, 2);
    }

    public function test_a_ticket_inside_its_target_tells_nobody(): void
    {
        Notification::fake();

        $agent = $this->inDepartment(Roles::AGENT);
        $this->makeTicket(['assignee_id' => $agent->getKey(), 'department_id' => $this->departmentId]);

        Artisan::call('sla:sweep');

        Notification::assertNothingSent();
    }

    public function test_an_unassigned_late_ticket_still_reaches_a_supervisor(): void
    {
        Notification::fake();

        $supervisor = $this->inDepartment(Roles::SUPERVISOR);

        $ticket = $this->makeTicket(['assignee_id' => null, 'department_id' => $this->departmentId]);
        $ticket->forceFill(['created_at' => CarbonImmutable::now('UTC')->subDays(10)])->save();

        Artisan::call('sla:sweep');

        // Nobody owns it, which is exactly why somebody senior needs to know.
        Notification::assertSentTo($supervisor, SlaWarning::class);
    }
}
