<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets\Admin;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Contracts\CategoryUsageProbe;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class CategoriesApiTest extends TestCase
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

    /** Stands in for the tickets table, which arrives in Story 5.x. */
    private function withTicketsUsing(int $count): void
    {
        $this->swap(CategoryUsageProbe::class, new class($count) implements CategoryUsageProbe
        {
            public function __construct(private readonly int $count) {}

            public function activeTicketCount(int $categoryId): int
            {
                return $this->count;
            }
        });
    }

    private function create(string $english = 'Billing', string $arabic = 'الفواتير'): int
    {
        return (int) $this->withIdempotencyKey()->postJson('/api/v1/admin/categories', [
            'name' => ['en' => $english, 'ar' => $arabic],
        ])->assertStatus(201)->json('id');
    }

    public function test_a_category_needs_a_name_in_both_languages(): void
    {
        $this->withIdempotencyKey()->postJson('/api/v1/admin/categories', ['name' => ['en' => 'Billing']])
            ->assertStatus(422);

        $this->assertDatabaseCount('ticket_categories', 0);
    }

    public function test_a_category_is_created_and_listed(): void
    {
        $this->create();

        $this->getJson('/api/v1/admin/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name.en', 'Billing')
            ->assertJsonPath('data.0.name.ar', 'الفواتير');
    }

    public function test_a_category_is_renamed(): void
    {
        $id = $this->create();

        $this->withIdempotencyKey()->patchJson("/api/v1/admin/categories/{$id}", [
            'name' => ['en' => 'Invoicing', 'ar' => 'الفوترة'],
        ])->assertOk()->assertJsonPath('name.en', 'Invoicing');

        $this->assertDatabaseHas('ticket_categories', ['id' => $id, 'name_en' => 'Invoicing']);
    }

    public function test_an_unused_category_is_deleted(): void
    {
        $this->withTicketsUsing(0);
        $id = $this->create();

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/categories/{$id}")
            ->assertOk()
            ->assertJsonPath('deleted', $id);

        $this->assertDatabaseMissing('ticket_categories', ['id' => $id]);
    }

    public function test_a_category_in_use_is_refused_with_the_count_and_a_way_to_see_them(): void
    {
        $this->withTicketsUsing(7);
        $id = $this->create();

        $response = $this->withIdempotencyKey()->deleteJson("/api/v1/admin/categories/{$id}")
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'tickets.category_in_use')
            // A refusal that just says "no" leaves the administrator guessing.
            // The count says how big the problem is; the path is where to fix it.
            ->assertJsonPath('count', 7);

        $this->assertStringContainsString((string) $id, (string) $response->json('path'));
        $this->assertDatabaseHas('ticket_categories', ['id' => $id]);
    }

    public function test_a_duplicate_name_is_refused_in_either_language(): void
    {
        $this->create('Billing', 'الفواتير');

        // Two categories reading the same in the agent's language are
        // indistinguishable in the picker, whichever language that is.
        $this->withIdempotencyKey()->postJson('/api/v1/admin/categories', [
            'name' => ['en' => 'Billing', 'ar' => 'شيء آخر'],
        ])->assertStatus(422);

        $this->withIdempotencyKey()->postJson('/api/v1/admin/categories', [
            'name' => ['en' => 'Something else', 'ar' => 'الفواتير'],
        ])->assertStatus(422);

        $this->assertDatabaseCount('ticket_categories', 1);
    }

    public function test_deleting_a_missing_category_is_a_404(): void
    {
        $this->withIdempotencyKey()->deleteJson('/api/v1/admin/categories/999999')->assertStatus(404);
    }

    public function test_categories_are_flat_by_construction(): void
    {
        $parent = $this->create('Parent', 'الأصل');

        $this->withIdempotencyKey()->postJson('/api/v1/admin/categories', [
            'name' => ['en' => 'Child', 'ar' => 'الفرع'],
            'parent_id' => $parent,
        ])->assertStatus(201);

        // There is no column to nest into, so a client that sends parent_id
        // gets a flat category rather than a silently-ignored hierarchy that
        // looks like it worked.
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('ticket_categories', 'parent_id'),
            'A parent_id column would make the flat-list guarantee unenforceable.',
        );
    }

    public function test_the_four_priorities_are_fixed_and_not_editable(): void
    {
        $this->getJson('/api/v1/admin/priorities')
            ->assertOk()
            ->assertJsonPath('editable', false)
            ->assertJsonPath('data.0.value', 'low')
            ->assertJsonPath('data.3.value', 'urgent');

        // No write route exists at all — not a route that answers 403.
        $this->withIdempotencyKey()->postJson('/api/v1/admin/priorities', ['value' => 'critical'])
            ->assertStatus(405);
    }

    public function test_a_supervisor_cannot_manage_categories(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->syncRoles([Roles::SUPERVISOR]);
        $this->actingAs($supervisor->refresh());

        $this->getJson('/api/v1/admin/categories')->assertStatus(403);
        $this->withIdempotencyKey()->postJson('/api/v1/admin/categories', [
            'name' => ['en' => 'Billing', 'ar' => 'الفواتير'],
        ])->assertStatus(403);
    }
}
