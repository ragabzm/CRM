<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Ticket;

/**
 * Shared fixtures for the lifecycle suites.
 *
 * Tickets are built through the real CreateTicket command rather than inserted:
 * a fixture that skipped the command would also skip the reference, the version
 * and the creation event, and the tests would then be asserting against a shape
 * the product never produces.
 */
trait InteractsWithLifecycle
{
    protected function makeTicket(?User $creator = null, ?int $departmentId = null): Ticket
    {
        $creator ??= User::factory()->create();

        return $this->app->make(CreateTicket::class)->handle(
            Actor::staff((string) $creator->getKey(), (string) $creator->name),
            new CreateTicketInput(
                subject: 'Invoice is wrong',
                description: 'Charged twice this month.',
                customerId: $this->customerId,
                channel: TicketChannel::Agent,
                departmentId: $departmentId,
            ),
        );
    }

    /** A ticket already held by someone. */
    protected function makeTicketHeldBy(User $holder, ?int $departmentId = null): Ticket
    {
        $ticket = $this->makeTicket($holder, $departmentId);
        $ticket->forceFill(['assignee_id' => $holder->getKey()])->save();

        return $ticket->refresh();
    }

    protected function agent(?int $departmentId = null): User
    {
        $user = User::factory()->create($departmentId !== null ? ['department_id' => $departmentId] : []);
        $user->syncRoles([Roles::AGENT]);

        return $user->refresh();
    }

    protected function supervisor(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([Roles::SUPERVISOR]);

        return $user->refresh();
    }
}
