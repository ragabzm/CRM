<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Security\Domain\Department;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Support\Str;

/**
 * Ticket fixtures, in one place.
 *
 * A ticket needs a customer, which needs a department, and the history tests
 * need several of each. Copying that chain into every file is how the copies
 * drift apart until one of them is testing a shape the application no longer
 * writes.
 */
trait MakesTickets
{
    private static int $ticketSequence = 0;

    protected function makeDepartment(string $name = 'Billing'): int
    {
        return (int) Department::firstOrCreate(['name' => $name], ['is_active' => true])->getKey();
    }

    protected function makeCustomer(?int $departmentId = null): string
    {
        $customer = new Customer([
            'reference' => Customer::mintReference(),
            'full_name' => 'Someone',
            'department_id' => $departmentId ?? $this->makeDepartment(),
            'state' => 'active',
        ]);

        $customer->setAttribute('id', (string) Str::ulid());
        $customer->save();

        return (string) $customer->getKey();
    }

    protected function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeTicket(array $attributes = []): Ticket
    {
        $ticket = new Ticket;

        $ticket->forceFill([
            'reference' => 'TKT-'.str_pad((string) ++self::$ticketSequence, 6, '0', STR_PAD_LEFT),
            'subject' => 'Subject',
            'description' => 'Body',
            'customer_id' => $attributes['customer_id'] ?? $this->makeCustomer(),
            'channel' => 'agent',
            'status' => 'open',
            'priority' => 'normal',
            'assignee_id' => null,
            'department_id' => null,
            'creator_type' => 'staff',
            'creator_id' => '1',
            'version' => 1,
            ...$attributes,
        ])->save();

        return $ticket->refresh();
    }
}
