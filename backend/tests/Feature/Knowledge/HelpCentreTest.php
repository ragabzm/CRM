<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * What a customer can reach, and everything they cannot.
 *
 * The test that matters most asks for an internal article BY ITS EXACT ID and
 * expects a 404 — the same answer as for an article that does not exist. A 403
 * would confirm the article is real, and "there is an internal article about
 * your problem that you may not read" is itself information.
 *
 * No account is needed. Requiring one to read an answer is a help centre that
 * only helps people who already got in, and the point of writing the answer
 * down was to stop somebody having to ask.
 */
final class HelpCentreTest extends TestCase
{
    use RefreshDatabase;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->categoryId = (int) DB::table('article_categories')->insertGetId([
            'name_en' => 'Billing',
            'name_ar' => 'الفوترة',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, array{string, string}>  $translations
     */
    private function article(
        array $translations,
        bool $internal = false,
        string $status = 'published',
        ?int $categoryId = null,
    ): string {
        $id = (string) Str::ulid();

        DB::table('articles')->insert([
            'id' => $id,
            'type' => 'faq',
            'category_id' => $categoryId ?? $this->categoryId,
            'internal_only' => $internal,
            'status' => $status,
            'default_locale' => array_key_first($translations),
            'has_been_published' => $status !== 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($translations as $locale => [$title, $body]) {
            DB::table('article_translations')->insert([
                'id' => (string) Str::ulid(),
                'article_id' => $id,
                'locale' => $locale,
                'title' => $title,
                'body' => $body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }

    public function test_the_help_centre_needs_no_account(): void
    {
        $this->article(['en' => ['Refund policy', '<p>Five working days.</p>']]);

        // Nobody is signed in. Deliberately.
        $this->getJson('/api/v1/help/articles')->assertOk();
    }

    public function test_an_internal_article_is_a_404_by_its_exact_id(): void
    {
        $internal = $this->article(['en' => ['Refunds — internal', '<p>Escalate over 500.</p>']], internal: true);

        $response = $this->getJson('/api/v1/help/articles/'.$internal);

        /*
         * Not a 403, and not a filtered page. The same answer a nonexistent id
         * gets, so neither is distinguishable from the other.
         */
        $response->assertNotFound();
        $this->assertSame('knowledge.article_unavailable', $response->json('code'));

        // And not a word of it in the body.
        $this->assertStringNotContainsString('Escalate over 500', (string) $response->getContent());
    }

    public function test_a_draft_and_an_archived_article_are_the_same_404(): void
    {
        foreach (['draft', 'archived'] as $status) {
            $id = $this->article(['en' => ["Refunds {$status}", '<p>Secret.</p>']], status: $status);

            $this->getJson('/api/v1/help/articles/'.$id)
                ->assertNotFound()
                ->assertJsonPath('code', 'knowledge.article_unavailable');
        }
    }

    public function test_a_link_to_an_article_since_archived_says_so_rather_than_showing_it(): void
    {
        $id = $this->article(['en' => ['Refund policy', '<p>Five working days.</p>']]);

        $this->getJson('/api/v1/help/articles/'.$id)->assertOk();

        DB::table('articles')->where('id', $id)->update(['status' => 'archived']);

        $response = $this->getJson('/api/v1/help/articles/'.$id);

        /*
         * A stated message, never the body of a withdrawn article. Somebody
         * following a two-year-old link should be told the answer is gone and
         * offered a way on, not shown something that was taken down on
         * purpose.
         */
        $response->assertNotFound();
        $this->assertStringContainsString('withdrawn', (string) $response->json('detail'));
        $this->assertStringNotContainsString('Five working days', (string) $response->getContent());
    }

    public function test_the_list_carries_only_public_published_articles(): void
    {
        $visible = $this->article(['en' => ['Refund policy', '<p>Five working days.</p>']]);
        $this->article(['en' => ['Internal notes', '<p>Escalate.</p>']], internal: true);
        $this->article(['en' => ['Unfinished', '<p>Draft.</p>']], status: 'draft');

        $response = $this->getJson('/api/v1/help/articles');

        $this->assertSame([$visible], array_column($response->json('data'), 'id'));
    }

    public function test_a_search_from_the_help_centre_cannot_surface_an_internal_article(): void
    {
        $this->article(['en' => ['Refunds — internal', '<p>Escalate over 500.</p>']], internal: true);
        $public = $this->article(['en' => ['Refunds', '<p>Five working days.</p>']]);

        $response = $this->getJson('/api/v1/help/articles?q=refunds');

        $this->assertSame([$public], array_column($response->json('data'), 'id'));
    }

    public function test_browsing_by_category_lists_only_categories_that_have_something_in_them(): void
    {
        $empty = (int) DB::table('article_categories')->insertGetId([
            'name_en' => 'Shipping',
            'name_ar' => 'الشحن',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->article(['en' => ['Refund policy', '<p>Five working days.</p>']]);
        // An internal article does not make its category worth listing.
        $this->article(['en' => ['Internal', '<p>x</p>']], internal: true, categoryId: $empty);

        $response = $this->getJson('/api/v1/help/articles');

        /*
         * A help centre listing eight categories where six open on nothing is
         * a help centre that looks broken.
         */
        $this->assertSame([$this->categoryId], array_column($response->json('categories'), 'id'));
    }

    public function test_the_reader_is_served_their_own_language_with_a_fallback(): void
    {
        $id = $this->article([
            'en' => ['Refund policy', '<p>Five working days.</p>'],
            'ar' => ['سياسة الاسترداد', '<p>خمسة أيام عمل.</p>'],
        ]);

        $arabic = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v1/help/articles/'.$id);
        $this->assertSame('ar', $arabic->json('served_locale'));
        $this->assertSame('سياسة الاسترداد', $arabic->json('title'));

        $englishOnly = $this->article(['en' => ['Shipping times', '<p>Two days.</p>']]);

        $fallback = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/help/articles/'.$englishOnly);

        // Served the default and TOLD which language it is — never a blank
        // page, and never an English page they assume is the Arabic one.
        $this->assertSame('en', $fallback->json('served_locale'));
        $this->assertSame('Shipping times', $fallback->json('title'));
    }

    public function test_the_customer_response_carries_no_staff_facts_at_all(): void
    {
        $id = $this->article(['en' => ['Refund policy', '<p>Five working days.</p>']]);

        $body = (string) $this->getJson('/api/v1/help/articles/'.$id)->getContent();

        /*
         * Checked against the RAW response. A leak arrives as an unexpected
         * key, and asserting only on the keys we expected would miss exactly
         * that.
         */
        foreach (['internal_only', 'has_been_published', 'published_by', 'archived_by', 'can_delete', 'status'] as $absent) {
            $this->assertStringNotContainsString($absent, $body, "The help centre leaked [{$absent}].");
        }
    }
}
