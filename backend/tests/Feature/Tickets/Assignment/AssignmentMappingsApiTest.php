<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets\Assignment;

use App\Models\User;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Assignment\AssignmentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Feature\Tickets\InteractsWithTickets;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The whole editor: a source, a target, and nothing else.
 *
 * What these tests mostly check is what the endpoint REFUSES — a second
 * mapping for one source, a target that does not exist — and what it admits
 * about a row that cannot fire. A mapping pointing at somebody who left is
 * worse than no mapping: the queue looks like it is being sorted while every
 * matching ticket quietly stays unassigned.
 */
final class AssignmentMappingsApiTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use MakesTickets;
    use RefreshDatabase;

    private User $agent;

    private int $otherDepartmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTickets(Roles::ADMINISTRATOR);

        $this->agent = User::factory()->create(['department_id' => $this->departmentId]);
        $this->agent->syncRoles([Roles::AGENT]);

        $this->otherDepartmentId = (int) Department::firstOrCreate(
            ['name' => 'Escalations'],
            ['is_active' => true],
        )->getKey();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMapping(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->postJson('/api/v1/admin/assignment-mappings', [
            'source_type' => 'category',
            'source_id' => $this->categoryId,
            'target_type' => 'agent',
            'target_id' => (int) $this->agent->getKey(),
            ...$overrides,
        ]);
    }

    public function test_a_mapping_is_created_listed_changed_and_removed(): void
    {
        $created = $this->createMapping();
        $created->assertCreated();
        $id = (int) $created->json('id');

        $list = $this->getJson('/api/v1/admin/assignment-mappings');
        $list->assertOk();
        $this->assertSame('category', $list->json('data.0.source_type'));
        $this->assertTrue($list->json('data.0.active'));

        $this->withIdempotencyKey()->patchJson("/api/v1/admin/assignment-mappings/{$id}", [
            'target_type' => 'department',
            'target_id' => $this->otherDepartmentId,
        ])->assertOk()->assertJsonPath('target_type', 'department');

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/assignment-mappings/{$id}")->assertOk();
        $this->assertSame(0, AssignmentMapping::query()->count());
    }

    public function test_the_editor_states_the_precedence_rather_than_leaving_it_to_be_discovered(): void
    {
        $list = $this->getJson('/api/v1/admin/assignment-mappings');

        /*
         * Category before department, from the server, so the interface reads
         * the rule rather than hard-coding the same sentence a second time and
         * eventually disagreeing with it.
         */
        $this->assertSame(['category', 'department'], $list->json('precedence'));
    }

    public function test_a_second_mapping_for_one_source_is_refused(): void
    {
        $this->createMapping()->assertCreated();

        $refusal = $this->createMapping(['target_type' => 'department', 'target_id' => $this->otherDepartmentId]);

        // Two rows for one source would need ordering to resolve, and the
        // point of this table is that there is nothing to resolve.
        $refusal->assertStatus(409);
        $this->assertSame('tickets.mapping_exists', $refusal->json('code'));
        $this->assertSame(1, AssignmentMapping::query()->count());
    }

    public function test_a_target_that_does_not_exist_is_refused(): void
    {
        $this->createMapping(['target_id' => 999999])->assertStatus(422)
            ->assertJsonPath('code', 'tickets.mapping_target_unknown');

        $this->createMapping(['source_id' => 999999])->assertStatus(422)
            ->assertJsonPath('code', 'tickets.mapping_source_unknown');
    }

    public function test_a_mapping_to_a_deactivated_agent_is_shown_as_inactive_with_the_reason(): void
    {
        $this->createMapping()->assertCreated();

        $this->agent->forceFill(['is_active' => false])->save();

        $list = $this->getJson('/api/v1/admin/assignment-mappings');

        $this->assertFalse($list->json('data.0.active'));
        $this->assertStringContainsString('deactivated', (string) $list->json('data.0.inactive_reason'));
    }

    public function test_removing_a_mapping_leaves_existing_tickets_where_they_are(): void
    {
        $created = $this->createMapping();
        $id = (int) $created->json('id');

        $ticket = $this->makeTicket([
            'customer_id' => $this->customerId,
            'category_id' => $this->categoryId,
            'assignee_id' => $this->agent->getKey(),
        ]);

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/assignment-mappings/{$id}")->assertOk();

        /*
         * A mapping decides where NEW work lands. Rewriting history because a
         * rule changed would move tickets out from under the people already
         * working them.
         */
        $this->assertSame((int) $this->agent->getKey(), $ticket->refresh()->assignee_id);
    }

    public function test_changes_are_audited_with_actor_and_both_sides(): void
    {
        $created = $this->createMapping();
        $id = (int) $created->json('id');

        $this->withIdempotencyKey()->patchJson("/api/v1/admin/assignment-mappings/{$id}", [
            'target_type' => 'department',
            'target_id' => $this->otherDepartmentId,
        ])->assertOk();

        $entries = DB::table('audit_entries')
            ->where('target_type', 'assignment_mapping')
            ->orderBy('occurred_at')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertNotNull($entries->last()->actor_id);
        $this->assertStringContainsString('agent', (string) $entries->last()->before);
        $this->assertStringContainsString('department', (string) $entries->last()->after);
    }

    public function test_an_agent_cannot_decide_where_everybody_else_s_work_lands(): void
    {
        $agent = User::factory()->create();
        $agent->syncRoles([Roles::AGENT]);
        $this->actingAs($agent->refresh());

        $this->getJson('/api/v1/admin/assignment-mappings')->assertForbidden();
        $this->createMapping()->assertForbidden();
    }
}
