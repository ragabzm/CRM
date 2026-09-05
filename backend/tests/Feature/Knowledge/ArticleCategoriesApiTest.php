<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * The article category list — its own, not the ticket one.
 *
 * The test that earns its place is the last one: the two lists are separate
 * tables, and an article filed under "Billing" has nothing to do with a ticket
 * category of the same name. They are related by meaning, not by a key.
 */
final class ArticleCategoriesApiTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles([Roles::ADMINISTRATOR]);
        $this->actingAs($user->refresh());
    }

    private function create(string $en, string $ar): string
    {
        return (string) $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/categories', [
            'name' => ['en' => $en, 'ar' => $ar],
        ])->assertCreated()->json('id');
    }

    public function test_categories_are_created_listed_renamed_and_removed(): void
    {
        $id = $this->create('Billing', 'الفوترة');

        $list = $this->getJson('/api/v1/admin/knowledge/categories');
        $list->assertOk();
        $this->assertSame('Billing', $list->json('data.0.name.en'));
        $this->assertSame('الفوترة', $list->json('data.0.name.ar'));
        $this->assertSame(0, $list->json('data.0.article_count'));

        $this->withIdempotencyKey()->patchJson("/api/v1/admin/knowledge/categories/{$id}", [
            'name' => ['en' => 'Payments', 'ar' => 'المدفوعات'],
        ])->assertOk()->assertJsonPath('name.en', 'Payments');

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/knowledge/categories/{$id}")->assertOk();
        $this->assertSame(0, DB::table('article_categories')->count());
    }

    public function test_a_category_with_articles_in_it_cannot_be_deleted(): void
    {
        $id = $this->create('Billing', 'الفوترة');

        $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
            'type' => 'faq',
            'category_id' => (int) $id,
            'default_locale' => 'en',
        ])->assertCreated();

        $refusal = $this->withIdempotencyKey()->deleteJson("/api/v1/admin/knowledge/categories/{$id}");

        $refusal->assertStatus(409);
        $this->assertSame('knowledge.category_in_use', $refusal->json('code'));

        /*
         * A count and a path, not a bare "cannot delete". The next question is
         * always "which ones?", and an answer somebody has to construct
         * themselves is how a rule turns into a support request.
         */
        $this->assertSame(1, $refusal->json('count'));
        $this->assertStringContainsString('category='.$id, (string) $refusal->json('path'));
    }

    public function test_a_duplicate_name_is_refused_in_either_language(): void
    {
        $this->create('Billing', 'الفوترة');

        $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/categories', [
            'name' => ['en' => 'Billing', 'ar' => 'شيء آخر'],
        ])->assertStatus(422);

        $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/categories', [
            'name' => ['en' => 'Something else', 'ar' => 'الفوترة'],
        ])->assertStatus(422);
    }

    public function test_the_article_list_and_the_ticket_list_are_not_the_same_list(): void
    {
        $articleCategory = $this->create('Billing', 'الفوترة');

        // The same NAME on the ticket side. Both are legitimate, and neither
        // knows about the other.
        $this->withIdempotencyKey()->postJson('/api/v1/admin/categories', [
            'name' => ['en' => 'Billing', 'ar' => 'الفوترة'],
        ])->assertCreated();

        $this->assertSame(1, DB::table('article_categories')->count());
        $this->assertSame(1, DB::table('ticket_categories')->count());

        // Deleting the ticket category leaves the article one exactly where it
        // was — no foreign key, no cascade, no surprise refiling.
        $ticketId = DB::table('ticket_categories')->value('id');
        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/categories/{$ticketId}")->assertOk();

        $this->assertSame(1, DB::table('article_categories')->count());
        $this->assertSame('Billing', DB::table('article_categories')->where('id', $articleCategory)->value('name_en'));
    }

    public function test_an_agent_may_read_the_list_but_not_change_it(): void
    {
        $id = $this->create('Billing', 'الفوترة');

        $agent = User::factory()->create();
        $agent->syncRoles([Roles::AGENT]);
        $this->actingAs($agent->refresh());

        $this->getJson('/api/v1/admin/knowledge/categories')->assertOk();

        /*
         * Writing an article is an agent's job; deciding how the whole library
         * is organised is not — a category renamed under forty articles is a
         * change everybody else has to live with.
         */
        $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/categories', [
            'name' => ['en' => 'Mine', 'ar' => 'لي'],
        ])->assertForbidden();

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/knowledge/categories/{$id}")->assertForbidden();
    }
}
