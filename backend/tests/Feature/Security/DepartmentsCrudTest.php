<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Http\ProblemDetails;
use App\Modules\Security\Contracts\DepartmentUsageProbe;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DepartmentsCrudTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrator = User::factory()->create();
        $administrator->syncRoles([Roles::ADMINISTRATOR]);
        $this->actingAs($administrator->refresh());
    }

    /** Replaces the usage probe so this suite needs no tickets table. */
    private function withActiveTickets(int $count): void
    {
        $this->swap(DepartmentUsageProbe::class, new class($count) implements DepartmentUsageProbe
        {
            public function __construct(private readonly int $count) {}

            public function activeTicketCount(int $departmentId): int
            {
                return $this->count;
            }
        });
    }

    public function test_an_administrator_creates_a_department(): void
    {
        $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => 'Billing'])
            ->assertStatus(201)
            ->assertJsonPath('name', 'Billing')
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('departments', ['name' => 'Billing', 'is_active' => true]);
    }

    public function test_a_department_can_be_renamed(): void
    {
        $department = Department::create(['name' => 'Billing', 'is_active' => true]);

        $this->withIdempotencyKey()->patchJson("/api/v1/departments/{$department->id}", ['name' => 'Accounts Receivable'])
            ->assertOk()
            ->assertJsonPath('name', 'Accounts Receivable');
    }

    public function test_an_arabic_name_is_accepted_and_measured_in_characters(): void
    {
        // max:120 counts characters, not bytes — otherwise an Arabic name would
        // be silently shorter than an English one.
        $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => 'قسم خدمة العملاء'])
            ->assertStatus(201)
            ->assertJsonPath('name', 'قسم خدمة العملاء');

        $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => str_repeat('ع', 120)])->assertStatus(201);
        $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => str_repeat('ع', 121)])->assertStatus(422);
    }

    public function test_names_are_unique_case_insensitively(): void
    {
        $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => 'Billing'])->assertStatus(201);

        // "Billing" and "billing" would be indistinguishable to a reader
        // choosing between them in a dropdown.
        $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => 'billing'])->assertStatus(422);
        $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => 'BILLING'])->assertStatus(422);
    }

    public function test_renaming_to_its_own_name_is_allowed(): void
    {
        $department = Department::create(['name' => 'Billing', 'is_active' => true]);

        $this->withIdempotencyKey()->patchJson("/api/v1/departments/{$department->id}", ['name' => 'Billing'])->assertOk();
    }

    public function test_a_department_with_no_active_tickets_deactivates(): void
    {
        $this->withActiveTickets(0);
        $department = Department::create(['name' => 'Billing', 'is_active' => true]);

        $this->withIdempotencyKey()->postJson("/api/v1/departments/{$department->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('is_active', false);
    }

    public function test_a_department_holding_active_tickets_is_refused(): void
    {
        $this->withActiveTickets(3);
        $department = Department::create(['name' => 'Deliveries', 'is_active' => true]);

        $response = $this->withIdempotencyKey()->postJson("/api/v1/departments/{$department->id}/deactivate");

        $response->assertStatus(409);
        $this->assertStringStartsWith(
            ProblemDetails::CONTENT_TYPE,
            (string) $response->headers->get('Content-Type'),
        );
        $response->assertJsonPath('code', 'security.department_has_active_tickets');

        /*
         * Refused, not confirmed away — and the refusal carries the COUNT and a
         * PATH, so the reader can go and deal with the tickets instead of
         * guessing how many there are.
         */
        $response->assertJsonPath('activeTicketCount', 3);
        $this->assertStringStartsWith(
            '/tickets?department='.$department->id,
            (string) $response->json('ticketsPath'),
        );
        $this->assertStringContainsString('3', (string) $response->json('detail'));
    }

    public function test_a_refused_deactivation_leaves_the_department_active(): void
    {
        $this->withActiveTickets(2);
        $department = Department::create(['name' => 'Deliveries', 'is_active' => true]);

        $this->withIdempotencyKey()->postJson("/api/v1/departments/{$department->id}/deactivate")->assertStatus(409);

        $this->assertTrue($department->refresh()->is_active);
    }

    public function test_there_is_no_delete_route(): void
    {
        $department = Department::create(['name' => 'Billing', 'is_active' => true]);

        // Deleting would orphan the historical department of everyone who ever
        // belonged to it.
        $this->withIdempotencyKey()->deleteJson("/api/v1/departments/{$department->id}")->assertStatus(405);
    }

    public function test_the_active_scope_filters_deactivated_rows(): void
    {
        Department::create(['name' => 'Active One', 'is_active' => true]);
        Department::create(['name' => 'Closed One', 'is_active' => false]);

        $this->assertSame(['Active One'], Department::query()->active()->pluck('name')->all());
        // The list endpoint still returns both: an administrator must be able
        // to see and reactivate a closed department.
        $this->assertCount(2, $this->getJson('/api/v1/departments')->json('data'));
    }

    public function test_a_supervisor_reads_the_department_list_but_cannot_change_it(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->syncRoles([Roles::SUPERVISOR]);
        $this->actingAs($supervisor->refresh());

        // Needed to file a customer under a team; not permission to invent one.
        $this->getJson('/api/v1/departments')->assertOk();
        $this->withIdempotencyKey()->postJson('/api/v1/departments', ['name' => 'Invented'])->assertStatus(403);
    }

    public function test_an_agent_reads_the_department_list(): void
    {
        $agent = User::factory()->create();
        $agent->syncRoles([Roles::AGENT]);
        $this->actingAs($agent->refresh());

        // It fills the filter on the customer list they use all day.
        $this->getJson('/api/v1/departments')->assertOk();
    }

    public function test_the_real_usage_probe_counts_real_tickets(): void
    {
        /*
         * Every other test here swaps the probe for a fake, which proves the
         * REFUSAL works but says nothing about the count being right. The
         * tickets table exists now, so the real implementation can finally be
         * exercised end to end — a probe that always answered zero would make
         * the whole rule silently permissive.
         */
        $department = Department::create(['name' => 'Support', 'is_active' => true]);

        $customer = new Customer([
            'reference' => Customer::mintReference(),
            'full_name' => 'Hana Yousef',
            'department_id' => $department->getKey(),
            'state' => 'active',
        ]);
        $customer->setAttribute('id', (string) Str::ulid());
        $customer->save();

        $ticket = $this->app->make(CreateTicket::class)->handle(
            Actor::staff('1', 'Root Admin'),
            new CreateTicketInput(
                subject: 'Still open',
                description: 'Needs someone.',
                customerId: (string) $customer->getKey(),
                channel: TicketChannel::Agent,
                departmentId: (int) $department->getKey(),
            ),
        );

        $this->withIdempotencyKey()
            ->postJson("/api/v1/departments/{$department->getKey()}/deactivate")
            ->assertStatus(409)
            ->assertJsonPath('activeTicketCount', 1);

        // ...and once the ticket is no longer active, the department frees up.
        $ticket->forceFill(['status' => 'closed'])->save();

        $this->withIdempotencyKey()
            ->postJson("/api/v1/departments/{$department->getKey()}/deactivate")
            ->assertOk()
            ->assertJsonPath('is_active', false);
    }
}
