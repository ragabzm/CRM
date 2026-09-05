<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Modules\Knowledge\Domain\Search\ArticleSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RunsAgainstRealPostgres;
use Tests\TestCase;

/**
 * The search path production actually runs.
 *
 * The suite runs on SQLite, where there is no tsvector, no pg_trgm and no
 * generated column — so every other test in this module exercises the LIKE
 * fallback and proves nothing about ranking, stemming or Arabic. This class
 * connects to a real Postgres and skips loudly when it cannot, because a guard
 * that silently passes where it cannot look is the thing it guards against.
 *
 * The test that earns its place is `test_the_trigram_half_is_not_optional`. It
 * issues the queries the in-ticket panel actually sends — partial words — and
 * asserts they come back. Remove the trigram index and it fails; that is the
 * whole point of writing it down.
 */
final class ArticleSearchPostgresTest extends TestCase
{
    use RunsAgainstRealPostgres;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useRealPostgres('the tsvector and trigram search path');

        $this->categoryId = (int) DB::table('article_categories')->insertGetId([
            'name_en' => 'Billing',
            'name_ar' => 'الفوترة',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->releaseRealPostgres();
        parent::tearDown();
    }

    /**
     * @param  array<string, array{string, string}>  $translations  locale => [title, body]
     */
    private function article(array $translations, bool $internal = false, string $status = 'published'): string
    {
        $id = (string) Str::ulid();

        DB::table('articles')->insert([
            'id' => $id,
            'type' => 'faq',
            'category_id' => $this->categoryId,
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

    private function search(string $term, string $locale = 'en', bool $customerOnly = false): array
    {
        return array_column(
            app(ArticleSearch::class)->search($term, $locale, $customerOnly),
            'id',
        );
    }

    public function test_it_really_is_using_postgres_full_text(): void
    {
        // Otherwise every assertion below is about the LIKE fallback.
        $this->assertTrue(app(ArticleSearch::class)->usesFullText());
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('article_translations', 'search_vector'));
    }

    public function test_the_title_outranks_the_body(): void
    {
        $titled = $this->article(['en' => ['Refunds', 'How we handle a request.']]);
        $mentioned = $this->article(['en' => ['Shipping', 'Sometimes a refund is issued after shipping.']]);

        $results = $this->search('refunds');

        /*
         * An article TITLED "Refunds" must beat one that mentions refunds in
         * paragraph nine. `ts_rank` only knows that because the generated
         * vector weights the title 'A' and the body 'B'.
         */
        $this->assertSame([$titled, $mentioned], $results);
    }

    public function test_stemming_finds_a_word_in_another_form(): void
    {
        $id = $this->article(['en' => ['Refund policy', 'We refund within five working days.']]);

        // Plain LIKE would not match "refunding" against "refund".
        $this->assertContains($id, $this->search('refunding'));
    }

    public function test_the_trigram_half_is_not_optional(): void
    {
        $arabic = $this->article(['ar' => ['الفواتير المكررة', 'لو اتخصم منك نفس المبلغ مرتين.']]);
        $english = $this->article(['en' => ['Refund policy', 'We refund within five working days.']]);

        /*
         * The partial words an agent has half-typed. Measured against this
         * Postgres: `websearch_to_tsquery('arabic', 'فوات')` matches NOTHING,
         * and neither does `websearch_to_tsquery('english', 'refun')` — no
         * stemmer can match a word nobody has finished.
         *
         * The in-ticket panel searches on every debounced keystroke, so most
         * of what it sends is exactly this. Drop the trigram indexes and this
         * test goes red.
         */
        foreach ([
            ['فوات', 'ar', $arabic],
            ['المكرر', 'ar', $arabic],
            ['refun', 'en', $english],
            ['polic', 'en', $english],
        ] as [$term, $locale, $expected]) {
            $this->assertContains(
                $expected,
                $this->search($term, $locale),
                "A partial query [{$term}] found nothing. The trigram index is the half that answers it.",
            );
        }
    }

    public function test_the_stemmer_alone_would_have_failed_those_queries(): void
    {
        $this->article(['ar' => ['الفواتير المكررة', 'لو اتخصم منك نفس المبلغ مرتين.']]);

        /*
         * Asserted directly against Postgres, so the claim in the test above
         * is demonstrated rather than assumed. If a future Postgres stems
         * partial words, this goes red and the comment beside it stops being
         * true — which is exactly when somebody should read it again.
         */
        $stemmerOnly = DB::selectOne(
            "select count(*) as c from article_translations
             where search_vector @@ websearch_to_tsquery('arabic', ?)",
            ['فوات'],
        );

        $this->assertSame(0, (int) $stemmerOnly->c);
    }

    public function test_a_bilingual_article_appears_once(): void
    {
        $id = $this->article([
            'en' => ['Refund policy', 'We refund within five working days.'],
            'ar' => ['سياسة الاسترداد', 'بنرد المبلغ خلال خمسة أيام عمل.'],
        ]);

        // One answer, not two. Returning it twice would push a different
        // answer off the panel.
        $this->assertSame([$id], $this->search('refund'));
    }

    public function test_a_customer_search_cannot_reach_an_internal_article(): void
    {
        $internal = $this->article(['en' => ['Refunds — internal', 'Escalate over 500.']], internal: true);
        $public = $this->article(['en' => ['Refunds', 'We refund within five working days.']]);

        $this->assertSame([$public], $this->search('refunds', 'en', customerOnly: true));

        // And staff see both.
        $this->assertEqualsCanonicalizing([$internal, $public], $this->search('refunds'));
    }

    public function test_a_customer_search_cannot_reach_a_draft_or_an_archived_article(): void
    {
        $this->article(['en' => ['Refunds draft', 'Not finished.']], status: 'draft');
        $this->article(['en' => ['Refunds withdrawn', 'Out of date.']], status: 'archived');
        $published = $this->article(['en' => ['Refunds', 'We refund within five working days.']]);

        $this->assertSame([$published], $this->search('refunds', 'en', customerOnly: true));
    }

    public function test_it_answers_well_inside_the_two_second_budget(): void
    {
        for ($i = 0; $i < 300; $i++) {
            $this->article(['en' => ["Article {$i} about billing", "Body number {$i} mentioning invoices and refunds."]]);
        }

        DB::statement('ANALYZE article_translations');

        $started = microtime(true);
        $this->search('refunds');
        $elapsed = (microtime(true) - $started) * 1000;

        $this->assertLessThan(
            2000,
            $elapsed,
            sprintf('Search took %.0fms at 300 articles; the budget is 2000ms.', $elapsed),
        );
    }
}
