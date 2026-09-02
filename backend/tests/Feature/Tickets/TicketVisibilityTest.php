<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Security\Domain\Department;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The row-level rule, tested against a real query.
 *
 * Runs against the REAL tickets table. It used to build a throwaway one,
 * because the rule shipped before the schema did; now that Story 4.1 has
 * landed, testing the rule against a table with different columns from the
 * real one would be testing a fiction.
 */
final class TicketVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** Keeps references unique and ordered without a sequence. */
    private static int $sequence = 0;

    private int $departmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->departmentId = (int) Department::firstOrCreate(
            ['name' => 'Billing'],
            ['is_active' => true],
        )->getKey();
    }

    /** A customer row, since tickets now carry a real foreign key. */
    private function customer(): string
    {
        $customer = new Customer([
            'reference' => Customer::mintReference(),
            'full_name' => 'Someone',
            'department_id' => $this->departmentId,
            'state' => 'active',
        ]);
        $customer->setAttribute('id', (string) Str::ulid());
        $customer->save();

        return (string) $customer->getKey();
    }

    private function actor(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->syncRoles([$role]);

        return $user->refresh();
    }

    private function ticket(array $attributes): string
    {
        $ticket = new Ticket;

        $ticket->forceFill([
            'reference' => 'TKT-'.str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT),
            'subject' => 'Subject',
            'description' => 'Body',
            'customer_id' => $attributes['customer_id'] ?? $this->customer(),
            'channel' => 'agent',
            'status' => $attributes['status'] ?? 'open',
            'priority' => 'normal',
            'assignee_id' => $attributes['assignee_id'] ?? null,
            'department_id' => $attributes['department_id'] ?? null,
            'creator_type' => 'staff',
            'creator_id' => '1',
            'version' => 1,
        ])->save();

        return (string) $ticket->getKey();
    }

    /** @return list<string> */
    private function visibleTo(\Illuminate\Contracts\Auth\Authenticatable $actor): array
    {
        return TicketVisibility::scopeForActor(Ticket::query(), $actor)
            ->orderBy('reference')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    public function test_an_administrator_sees_every_ticket(): void
    {
        $someone = User::factory()->create();

        $mine = $this->ticket(['assignee_id' => $someone->getKey()]);
        $unassigned = $this->ticket(['assignee_id' => null]);

        $this->assertSame([$mine, $unassigned], $this->visibleTo($this->actor(Roles::ADMINISTRATOR)));
    }

    public function test_a_supervisor_sees_every_ticket(): void
    {
        // Supervision that cannot see the queue is not supervision.
        $a = $this->ticket(['assignee_id' => User::factory()->create()->getKey()]);
        $b = $this->ticket(['assignee_id' => null]);

        $this->assertSame([$a, $b], $this->visibleTo($this->actor(Roles::SUPERVISOR)));
    }

    public function test_an_agent_sees_their_own_and_the_unassigned_pool(): void
    {
        $agent = $this->actor(Roles::AGENT);

        $own = $this->ticket(['assignee_id' => $agent->id]);
        $unassigned = $this->ticket(['assignee_id' => null]);
        $someoneElses = $this->ticket(['assignee_id' => User::factory()->create()->getKey()]);

        $visible = $this->visibleTo($agent);

        // The pool is where an agent picks their next ticket; excluding it
        // would make new work invisible to everyone who could action it.
        $this->assertContains($own, $visible);
        $this->assertContains($unassigned, $visible);
        $this->assertNotContains($someoneElses, $visible);
    }

    public function test_the_agent_clause_cannot_widen_an_existing_filter(): void
    {
        $agent = $this->actor(Roles::AGENT);

        $mineOpen = $this->ticket(['assignee_id' => $agent->id, 'status' => 'open']);
        $this->ticket(['assignee_id' => null, 'status' => 'closed']);

        // The OR is wrapped, so it cannot escape and pull closed tickets back
        // into a query that already excluded them.
        $visible = TicketVisibility::scopeForActor(
            Ticket::query()->where('status', 'open'),
            $agent,
        )->pluck('id')->map(static fn ($id): string => (string) $id)->all();

        $this->assertSame([$mineOpen], $visible);
    }

    public function test_a_customer_sees_only_their_own(): void
    {
        $mine = $this->customer();
        $theirs = $this->ticket(['customer_id' => $mine]);
        $other = $this->ticket(['customer_id' => $this->customer()]);
        $customer = new CustomerActor($mine);
        $unassigned = $this->ticket(['assignee_id' => null]);

        $visible = $this->visibleTo($customer);

        $this->assertSame([$theirs], $visible);
        $this->assertNotContains($other, $visible);
        $this->assertNotContains($unassigned, $visible);
    }

    public function test_a_customer_with_no_linked_record_sees_nothing(): void
    {
        $customer = new CustomerActor(null);
        $this->ticket([]);
        $this->ticket([]);

        // `where(column, null)` would match every row with a null customer_id —
        // the classic fail-open.
        $this->assertSame([], $this->visibleTo($customer));
    }

    public function test_an_actor_with_no_role_sees_nothing(): void
    {
        $stranger = User::factory()->create();
        $this->ticket(['assignee_id' => null]);

        // Failing closed is the only safe default for a rule about who may see
        // what.
        $this->assertSame([], $this->visibleTo($stranger->refresh()));
    }

    public function test_department_does_not_affect_visibility(): void
    {
        $agent = $this->actor(Roles::AGENT, ['department_id' => null]);

        $otherDepartment = $this->ticket(['assignee_id' => $agent->id, 'department_id' => $this->departmentId]);

        // Department is a grouping and a filter. A ticket surfacing outside the
        // caller's department is not a leak — treating it as one turns a filter
        // into an authorization mechanism nobody wrote a test for.
        $this->assertContains($otherDepartment, $this->visibleTo($agent));
    }
}

/**
 * A customer actor.
 *
 * NOT an App\Models\User: `users` is the staff table and has no customer_id,
 * and Story 2.1 deliberately put portal customers in their own table behind
 * their own guard. This stub carries the shape TicketVisibility reads so the
 * customer branch is genuinely exercised.
 *
 * Still unresolved after Story 4.1: portal accounts use the `portal` guard and
 * carry no roles, so this branch is specified and tested but not yet reachable
 * in production. The portal ticket list is where it has to be settled.
 */
final class CustomerActor implements \Illuminate\Contracts\Auth\Authenticatable
{
    public function __construct(private readonly ?string $customerId) {}

    public function hasRole(string $role): bool
    {
        return $role === Roles::CUSTOMER;
    }

    public function getAttribute(string $key): mixed
    {
        return $key === 'customer_id' ? $this->customerId : null;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return 1;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}

/** Minimal model over the fixture table. */
final class TicketStub extends Model
{
    protected $table = 'tickets';

    public $timestamps = false;

    protected $guarded = [];
}
