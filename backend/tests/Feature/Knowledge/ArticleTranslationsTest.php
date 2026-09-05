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
 * One article, one or two languages, and what a reader is given.
 *
 * The behaviour that matters most is the one nobody notices when it works: a
 * reader whose language is missing is served the article's default and TOLD
 * which language they are reading. The alternatives are a blank page, or worse,
 * an English page they assume is the Arabic version.
 */
final class ArticleTranslationsTest extends TestCase
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
            'name_en' => 'Account',
            'name_ar' => 'الحساب',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        $user->syncRoles([Roles::ADMINISTRATOR]);
        $this->actingAs($user->refresh());
    }

    private function article(string $defaultLocale = 'en'): string
    {
        return (string) $this->withIdempotencyKey()->postJson('/api/v1/admin/knowledge/articles', [
            'type' => 'help',
            'category_id' => $this->categoryId,
            'default_locale' => $defaultLocale,
        ])->json('id');
    }

    private function write(string $id, string $locale, string $title, string $body): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->putJson(
            "/api/v1/admin/knowledge/articles/{$id}/translations/{$locale}",
            ['title' => $title, 'body' => $body],
        );
    }

    public function test_an_arabic_only_article_is_complete(): void
    {
        $id = $this->article('ar');

        $this->write($id, 'ar', 'كيف أغيّر كلمة السر', '<p>من صفحة الحساب.</p>')->assertOk();

        // Complete, not half-finished: it publishes, and it has everything a
        // reader of its own language needs.
        $this->assertSame(['ar'], $this->getJson("/api/v1/admin/knowledge/articles/{$id}")->json('available_locales'));

        $this->withIdempotencyKey()->postJson("/api/v1/admin/knowledge/articles/{$id}/publish")->assertOk();
    }

    public function test_the_body_is_sanitised_before_it_is_ever_stored(): void
    {
        $id = $this->article();

        $this->write($id, 'en', 'Resetting a password', '<p>Click here.</p><script>alert(1)</script>')->assertOk();

        /*
         * Read from the COLUMN, not from the response. Sanitising on the way
         * out would leave the dangerous version in the database for any second
         * reader — an export, a search index, a later feature — to find.
         */
        $stored = (string) DB::table('article_translations')->where('article_id', $id)->value('body');

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('alert(1)', $stored);
        $this->assertStringContainsString('Click here.', $stored);
    }

    public function test_a_body_that_sanitises_away_to_nothing_is_refused_rather_than_saved_empty(): void
    {
        $id = $this->article();

        $response = $this->write($id, 'en', 'Nothing', '<script>alert(1)</script><style>p{}</style>');

        $response->assertStatus(422);
        $this->assertSame('knowledge.body_empty_after_sanitising', $response->json('code'));
        // And the message says what IS allowed, so the author can fix it.
        $this->assertStringContainsString('lists', (string) $response->json('detail'));

        $this->assertSame(0, DB::table('article_translations')->count());
    }

    public function test_writing_the_same_language_twice_replaces_rather_than_duplicates(): void
    {
        $id = $this->article();

        $this->write($id, 'en', 'First title', '<p>First body.</p>')->assertOk();
        $this->write($id, 'en', 'Second title', '<p>Second body.</p>')->assertOk();

        // "Which English version?" must not be a question with two answers.
        $this->assertSame(1, DB::table('article_translations')->where('article_id', $id)->count());
        $this->assertSame('Second title', DB::table('article_translations')->where('article_id', $id)->value('title'));
    }

    public function test_a_reader_gets_their_own_language_when_it_exists(): void
    {
        $id = $this->article();
        $this->write($id, 'en', 'How refunds work', '<p>Five working days.</p>')->assertOk();
        $this->write($id, 'ar', 'كيف يعمل الاسترداد', '<p>خمسة أيام عمل.</p>')->assertOk();

        $arabic = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson("/api/v1/admin/knowledge/articles/{$id}");

        $arabic->assertOk();
        $this->assertSame('ar', $arabic->json('served_locale'));
        $this->assertSame('كيف يعمل الاسترداد', $arabic->json('title'));
    }

    public function test_a_reader_whose_language_is_missing_is_served_the_default_and_told_so(): void
    {
        $id = $this->article('en');
        $this->write($id, 'en', 'How refunds work', '<p>Five working days.</p>')->assertOk();

        $arabic = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson("/api/v1/admin/knowledge/articles/{$id}");

        $arabic->assertOk();

        /*
         * Served English, and SAYS so. Never a blank page, and never an
         * English page a reader assumes is the Arabic one.
         */
        $this->assertSame('en', $arabic->json('served_locale'));
        $this->assertSame('How refunds work', $arabic->json('title'));
        $this->assertNotNull($arabic->json('body'));
    }

    public function test_the_default_language_version_cannot_be_removed_while_it_is_the_fallback(): void
    {
        $id = $this->article('en');
        $this->write($id, 'en', 'How refunds work', '<p>Five working days.</p>')->assertOk();
        $this->write($id, 'ar', 'كيف يعمل الاسترداد', '<p>خمسة أيام عمل.</p>')->assertOk();

        $refusal = $this->withIdempotencyKey()
            ->deleteJson("/api/v1/admin/knowledge/articles/{$id}/translations/en");

        $refusal->assertStatus(422);
        $this->assertSame('knowledge.default_translation_required', $refusal->json('code'));
        $this->assertSame(2, DB::table('article_translations')->where('article_id', $id)->count());
    }

    public function test_a_non_default_language_version_can_be_removed(): void
    {
        $id = $this->article('en');
        $this->write($id, 'en', 'How refunds work', '<p>Five working days.</p>')->assertOk();
        $this->write($id, 'ar', 'كيف يعمل الاسترداد', '<p>خمسة أيام عمل.</p>')->assertOk();

        $this->withIdempotencyKey()
            ->deleteJson("/api/v1/admin/knowledge/articles/{$id}/translations/ar")
            ->assertOk();

        $this->assertSame(['en'], $this->getJson("/api/v1/admin/knowledge/articles/{$id}")->json('available_locales'));
    }

    public function test_the_list_shows_a_title_a_person_can_read_not_an_identifier(): void
    {
        $id = $this->article('en');
        $this->write($id, 'en', 'How refunds work', '<p>Five working days.</p>')->assertOk();

        /*
         * On the LIST, not only on the record. A list of ULIDs is a list
         * nobody can find anything in — and the identifier is the one thing
         * about an article that means nothing to the person reading it.
         */
        $list = $this->getJson('/api/v1/admin/knowledge/articles');

        $list->assertOk();
        $this->assertSame('How refunds work', $list->json('data.0.title'));
    }

    public function test_the_list_title_follows_the_same_fallback_as_the_record(): void
    {
        $id = $this->article('en');
        $this->write($id, 'en', 'How refunds work', '<p>Five working days.</p>')->assertOk();

        $arabic = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/admin/knowledge/articles');

        // No Arabic version, so the default is shown — the same rule the
        // record follows, rather than a blank cell.
        $this->assertSame('How refunds work', $arabic->json('data.0.title'));
    }

    public function test_an_article_with_no_words_yet_has_no_title_rather_than_a_wrong_one(): void
    {
        $this->article('en');

        $list = $this->getJson('/api/v1/admin/knowledge/articles');

        $this->assertNull($list->json('data.0.title'));
    }

    public function test_a_language_the_product_does_not_have_is_refused(): void
    {
        $id = $this->article();

        $this->write($id, 'fr', 'Comment', '<p>Bonjour.</p>')->assertStatus(422);
    }
}
