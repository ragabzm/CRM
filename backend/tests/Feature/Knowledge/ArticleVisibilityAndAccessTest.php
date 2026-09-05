<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\User;
use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\Enum\ArticleStatus;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Who may see an article, and who may put one in front of a customer.
 *
 * Two independent axes. `internal_only` says who it is FOR; `status` says
 * whether it is READY. A customer sees an article only where both agree, and
 * the exclusion happens in the query rather than in the interface — visibility
 * decided at render leaks the first time somebody writes a second read path
 * and forgets it.
 */
final class ArticleVisibilityAndAccessTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->categoryId = (int) DB::table('article_categories')->insertGetId([
            'name_en' => 'Billing',
            'name_ar' => 'الفوترة',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());

        return $user;
    }

    private function seedArticle(bool $internalOnly, ArticleStatus $status): string
    {
        $id = (string) Str::ulid();

        DB::table('articles')->insert([
            'id' => $id,
            'type' => 'faq',
            'category_id' => $this->categoryId,
            'internal_only' => $internalOnly,
            'status' => $status->value,
            'default_locale' => 'en',
            'has_been_published' => $status !== ArticleStatus::Draft,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_every_combination_of_the_two_axes_is_expressible(): void
    {
        /*
         * Four states, all legitimate. An internal published article is the
         * agent handbook; a public draft is an answer somebody is still
         * writing for customers. Collapsing the two axes into one status would
         * make two of these four inexpressible.
         */
        foreach ([true, false] as $internal) {
            foreach (ArticleStatus::cases() as $status) {
                $id = $this->seedArticle($internal, $status);

                $article = Article::query()->findOrFail($id);

                $this->assertSame($internal, $article->internal_only);
                $this->assertSame($status, $article->status);
            }
        }

        $this->assertSame(6, Article::query()->count());
    }

    public function test_a_customer_sees_only_what_is_both_public_and_published(): void
    {
        $visible = $this->seedArticle(internalOnly: false, status: ArticleStatus::Published);

        $this->seedArticle(internalOnly: true, status: ArticleStatus::Published);
        $this->seedArticle(internalOnly: false, status: ArticleStatus::Draft);
        $this->seedArticle(internalOnly: false, status: ArticleStatus::Archived);
        $this->seedArticle(internalOnly: true, status: ArticleStatus::Draft);

        /*
         * Asserted against the QUERY SCOPE, which is where the rule lives. A
         * test that filtered a full result set in PHP would pass even if the
         * scope did nothing, and the scope is the thing every future read path
         * will reuse.
         */
        $ids = Article::query()->tap(Article::scopeCustomerVisible(...))->pluck('id')->all();

        $this->assertSame([$visible], $ids);
    }

    public function test_an_agent_reads_and_writes_but_does_not_publish(): void
    {
        $this->actingAsRole(Roles::AGENT);

        $this->getJson('/api/v1/admin/knowledge/articles')->assertOk();

        $created = $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
            'type' => 'solution',
            'category_id' => $this->categoryId,
            'default_locale' => 'en',
        ]);
        $created->assertCreated();

        $id = (string) $created->json('id');

        $this->withIdempotencyKey()->putJson("/api/v1/admin/knowledge/articles/{$id}/translations/en", [
            'title' => 'What I worked out on that ticket',
            'body' => '<p>Restart the sync job.</p>',
        ])->assertOk();

        /*
         * And stops there. Putting an answer in front of every customer is a
         * decision with an audience, and it can never be undone — a published
         * article can only ever be archived.
         */
        $this->withIdempotencyKey()
            ->postJson("/api/v1/admin/knowledge/articles/{$id}/publish")
            ->assertForbidden();
    }

    public function test_a_supervisor_publishes(): void
    {
        $this->actingAsRole(Roles::SUPERVISOR);

        $id = (string) $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
            'type' => 'faq',
            'category_id' => $this->categoryId,
            'default_locale' => 'en',
        ])->json('id');

        $this->withIdempotencyKey()->putJson("/api/v1/admin/knowledge/articles/{$id}/translations/en", [
            'title' => 'How refunds work',
            'body' => '<p>Five working days.</p>',
        ])->assertOk();

        $this->withIdempotencyKey()
            ->postJson("/api/v1/admin/knowledge/articles/{$id}/publish")
            ->assertOk();
    }

    public function test_a_customer_account_reaches_none_of_it(): void
    {
        $this->actingAsRole(Roles::CUSTOMER);

        $this->getJson('/api/v1/admin/knowledge/articles')->assertForbidden();
        $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
            'type' => 'faq',
            'category_id' => $this->categoryId,
            'default_locale' => 'en',
        ])->assertForbidden();
    }

    public function test_the_type_is_a_label_and_filters_and_nothing_else(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        foreach (['faq', 'help', 'solution', 'guide'] as $type) {
            $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
                'type' => $type,
                'category_id' => $this->categoryId,
                'default_locale' => 'en',
            ])->assertCreated();
        }

        foreach (['faq', 'help', 'solution', 'guide'] as $type) {
            $response = $this->getJson('/api/v1/admin/knowledge/articles?type='.$type);

            $response->assertOk();
            $this->assertCount(1, $response->json('data'));
            $this->assertSame($type, $response->json('data.0.type'));

            /*
             * Same permissions, same lifecycle, same everything. The type
             * changes what a filter returns and nothing whatsoever besides —
             * this is the assertion that catches somebody branching on it.
             */
            $this->assertTrue($response->json('data.0.can_delete'));
            $this->assertSame('draft', $response->json('data.0.status'));
        }
    }
}
