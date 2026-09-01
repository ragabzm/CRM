<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The row-level rule, tested against a real query.
 *
 * A fake tickets table is created here rather than waiting for Story 4.1: the
 * rule is the deliverable, and asserting it by inspecting SQL strings would
 * pass for a query that returns the wrong rows.
 */
final class TicketVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Schema::create('tickets', function ($table): void {
            $table->id();
            $table->string('subject');
            $table->foreignId('assignee_id')->nullable();
            $table->foreignId('customer_id')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('department_id')->nullable();
        });
    }

    private function actor(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->syncRoles([$role]);

        return $user->refresh();
    }

    private function ticket(array $attributes): int
    {
        return TicketStub::query()->create($attributes + ['subject' => 'Subject'])->id;
    }

    /** @return list<int> */
    private function visibleTo(\Illuminate\Contracts\Auth\Authenticatable $actor): array
    {
        return TicketVisibility::scopeForActor(TicketStub::query(), $actor)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    public function test_an_administrator_sees_every_ticket(): void
    {
        $mine = $this->ticket(['assignee_id' => 999]);
        $unassigned = $this->ticket(['assignee_id' => null]);

        $this->assertSame([$mine, $unassigned], $this->visibleTo($this->actor(Roles::ADMINISTRATOR)));
    }

    public function test_a_supervisor_sees_every_ticket(): void
    {
        // Supervision that cannot see the queue is not supervision.
        $a = $this->ticket(['assignee_id' => 999]);
        $b = $this->ticket(['assignee_id' => null]);

        $this->assertSame([$a, $b], $this->visibleTo($this->actor(Roles::SUPERVISOR)));
    }

    public function test_an_agent_sees_their_own_and_the_unassigned_pool(): void
    {
        $agent = $this->actor(Roles::AGENT);

        $own = $this->ticket(['assignee_id' => $agent->id]);
        $unassigned = $this->ticket(['assignee_id' => null]);
        $someoneElses = $this->ticket(['assignee_id' => $agent->id + 500]);

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
            TicketStub::query()->where('status', 'open'),
            $agent,
        )->pluck('id')->all();

        $this->assertSame([$mineOpen], $visible);
    }

    public function test_a_customer_sees_only_their_own(): void
    {
        $customer = new CustomerActor(7);

        $theirs = $this->ticket(['customer_id' => 7]);
        $other = $this->ticket(['customer_id' => 8]);
        $unassigned = $this->ticket(['assignee_id' => null]);

        $visible = $this->visibleTo($customer);

        $this->assertSame([$theirs], $visible);
        $this->assertNotContains($other, $visible);
        $this->assertNotContains($unassigned, $visible);
    }

    public function test_a_customer_with_no_linked_record_sees_nothing(): void
    {
        $customer = new CustomerActor(null);
        $this->ticket(['customer_id' => null]);
        $this->ticket(['customer_id' => 7]);

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

        $otherDepartment = $this->ticket(['assignee_id' => $agent->id, 'department_id' => 42]);

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
 * TODO(Story 4.1 / portal): decide which identity actually holds the `customer`
 * role at runtime. Portal accounts use the `portal` guard and currently carry no
 * roles at all, so today this branch is specified and tested but unreachable in
 * production — worth settling before the ticket list ships.
 */
final class CustomerActor implements \Illuminate\Contracts\Auth\Authenticatable
{
    public function __construct(private readonly ?int $customerId) {}

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
