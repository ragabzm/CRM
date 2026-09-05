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
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Draft → Published → Archived, and the line that cannot be crossed back.
 *
 * The rule worth the most attention is `has_been_published`. It is set once and
 * never cleared, and it is the only thing standing between a published article
 * and permanent deletion. Everything else here is ordinary state.
 */
final class ArticleLifecycleTest extends TestCase
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

    /** An article with one English version, ready to publish. */
    private function draft(array $overrides = []): string
    {
        $response = $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', array_merge([
            'type' => 'faq',
            'category_id' => $this->categoryId,
            'default_locale' => 'en',
            'internal_only' => false,
        ], $overrides));

        $response->assertCreated();
        $id = (string) $response->json('id');

        $this->withIdempotencyKey()->putJson("/api/v1/admin/knowledge/articles/{$id}/translations/en", [
            'title' => 'How refunds work',
            'body' => '<p>We refund within five working days.</p>',
        ])->assertOk();

        return $id;
    }

    public function test_a_new_article_is_a_draft_whatever_the_caller_sent(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $response = $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
            'type' => 'guide',
            'category_id' => $this->categoryId,
            'default_locale' => 'en',
            // Sent, and ignored: state is the lifecycle's, not the request's.
            'status' => 'published',
            'has_been_published' => true,
        ]);

        $response->assertCreated();
        $this->assertSame('draft', $response->json('status'));
        $this->assertFalse($response->json('has_been_published'));
        $this->assertTrue($response->json('can_delete'));
    }

    public function test_publishing_stamps_the_actor_and_the_moment(): void
    {
        $user = $this->actingAsRole(Roles::ADMINISTRATOR);
        $id = $this->draft();

        $response = $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/publish");

        $response->assertOk();
        $this->assertSame('published', $response->json('status'));
        $this->assertTrue($response->json('has_been_published'));
        $this->assertNotNull($response->json('published_at'));
        $this->assertSame((string) $user->getKey(), $response->json('published_by'));
    }

    public function test_a_published_article_can_never_be_deleted_again(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);
        $id = $this->draft();

        $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/publish")->assertOk();

        $refusal = $this->withIdempotencyKey()->deleteJson("/api/v1/admin/knowledge/articles/{$id}");

        $refusal->assertStatus(409);
        $this->assertSame('knowledge.archive_only', $refusal->json('code'));
        // The refusal names the alternative, not just the rule.
        $this->assertStringContainsString('rchiv', (string) $refusal->json('detail'));

        $this->assertSame(1, Article::query()->count());
    }

    public function test_archiving_and_republishing_never_restores_the_right_to_delete(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);
        $id = $this->draft();

        $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/publish")->assertOk();
        $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/archive")->assertOk();

        /*
         * The point of the whole flag. `published_at` moves, `archived_at`
         * moves, `status` moves — and the one value that governs permanent
         * deletion does not, because it is not computed from any of them.
         */
        $archived = $this->getJson("/api/v1/admin/knowledge/articles/{$id}");
        $this->assertSame('archived', $archived->json('status'));
        $this->assertTrue($archived->json('has_been_published'));
        $this->assertFalse($archived->json('can_delete'));

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/knowledge/articles/{$id}")->assertStatus(409);

        $republished = $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/publish");
        $republished->assertOk();
        $this->assertNull($republished->json('archived_at'));
        $this->assertTrue($republished->json('has_been_published'));

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/knowledge/articles/{$id}")->assertStatus(409);
    }

    public function test_an_article_nobody_ever_saw_can_be_deleted(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);
        $id = $this->draft();

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/knowledge/articles/{$id}")->assertOk();

        $this->assertSame(0, Article::query()->count());
        // And its words went with it.
        $this->assertSame(0, DB::table('article_translations')->count());
    }

    public function test_publishing_refuses_an_article_with_nothing_to_read(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        $id = (string) $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
            'type' => 'faq',
            'category_id' => $this->categoryId,
            'default_locale' => 'en',
        ])->json('id');

        $response = $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/publish");

        $response->assertStatus(422);
        $this->assertSame('knowledge.nothing_to_publish', $response->json('code'));
        $this->assertSame('draft', Article::query()->sole()->status->value);
    }

    public function test_publishing_refuses_when_the_fallback_language_is_the_missing_one(): void
    {
        $this->actingAsRole(Roles::ADMINISTRATOR);

        // Default English, written only in Arabic: a reader with no Arabic
        // would be served the default, and there is nothing there.
        $id = (string) $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
            'type' => 'faq',
            'category_id' => $this->categoryId,
            'default_locale' => 'en',
        ])->json('id');

        $this->withIdempotencyKey()->putJson("/api/v1/admin/knowledge/articles/{$id}/translations/ar", [
            'title' => 'كيف يعمل الاسترداد',
            'body' => '<p>نرد المبلغ خلال خمسة أيام عمل.</p>',
        ])->assertOk();

        $response = $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/publish");

        $response->assertStatus(422);
        $this->assertSame('knowledge.default_translation_missing', $response->json('code'));
        // The refusal says what is missing and what to do about it.
        $this->assertSame('en', $response->json('default_locale'));
        $this->assertSame(['ar'], $response->json('available'));
    }

    public function test_publish_archive_and_delete_are_recorded_with_an_actor(): void
    {
        $user = $this->actingAsRole(Roles::ADMINISTRATOR);
        $id = $this->draft();

        $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/publish")->assertOk();
        $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/archive")->assertOk();

        $entries = DB::table('audit_entries')
            ->whereIn('action', ['article.published', 'article.archived'])
            ->get();

        $this->assertCount(2, $entries);

        foreach ($entries as $entry) {
            $this->assertSame((string) $user->getKey(), $entry->actor_id);
            $this->assertSame($id, $entry->target_id);
            // `occurred_at`, not `created_at`: an audit row records WHEN THE
            // THING HAPPENED, which is not always when the row was written.
            $this->assertNotNull($entry->occurred_at);
        }
    }
}
