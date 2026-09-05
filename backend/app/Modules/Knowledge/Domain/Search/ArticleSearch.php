<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain\Search;

use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\Enum\ArticleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finding the answer somebody already wrote.
 *
 * ONE search, owned by Knowledge, serving both surfaces. There is no
 * cross-module search endpoint, no federated query and no search service —
 * Tickets never queries article tables and the portal never reaches the staff
 * one. What differs between the two callers is a single boolean, and it
 * changes the QUERY rather than the response.
 *
 * Two matchers, both required:
 *
 *   The tsvector handles whole words with stemming and ranks by weight, so an
 *   article TITLED "Refunds" beats one that mentions refunds in paragraph
 *   nine.
 *
 *   Trigrams handle the word nobody has finished typing. That is not an edge
 *   case: the in-ticket panel searches on every debounced keystroke, so most
 *   of what reaches here is a partial word. See the index migration for the
 *   measurements.
 *
 * SQLite gets LIKE containment. The test suite runs on SQLite, which is
 * exactly why `ArticleSearchPostgresTest` exists and skips loudly.
 */
final class ArticleSearch
{
    public const DEFAULT_LIMIT = 10;

    public const MAX_LIMIT = 50;

    /**
     * @param  bool  $customerVisibleOnly  True narrows to public AND published,
     *                                     at the QUERY. The portal passes true.
     * @return list<array<string, mixed>>
     */
    public function search(
        string $term,
        string $locale,
        bool $customerVisibleOnly,
        int $limit = self::DEFAULT_LIMIT,
    ): array {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $query = Article::query()->with('translations');

        if ($customerVisibleOnly) {
            /*
             * Through the model's own scope, which is the one place that
             * knows what "a customer may see this" means. A second copy of
             * `internal_only = false AND status = published` here would be a
             * second thing to get wrong — and getting it wrong on this side
             * means showing a customer an internal article.
             */
            $query->tap(Article::scopeCustomerVisible(...));
        }

        return $this->usesFullText()
            ? $this->byRank($query, $term, $locale, min($limit, self::MAX_LIMIT))
            : $this->byContainment($query, $term, min($limit, self::MAX_LIMIT));
    }

    public function usesFullText(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * The production path.
     *
     * @param  Builder<Article>  $query
     * @return list<array<string, mixed>>
     */
    private function byRank(Builder $query, string $term, string $locale, int $limit): array
    {
        $configuration = $locale === 'ar' ? 'arabic' : 'english';
        $like = '%'.mb_strtolower($term).'%';

        /*
         * Rank from the tsvector, presence from either matcher.
         *
         * A trigram-only hit ranks 0 and still appears — below every stemmed
         * match, which is the right order: somebody who typed three letters
         * wants to see something, and somebody who typed a whole word wants
         * the article about it first.
         */
        $rank = "ts_rank(t.search_vector, websearch_to_tsquery(?, ?))";

        $rows = $query
            ->join('article_translations as t', 't.article_id', '=', 'articles.id')
            ->select('articles.*', 't.locale as matched_locale', 't.title as matched_title')
            ->selectRaw("{$rank} as rank", [$configuration, $term])
            ->where(function (Builder $where) use ($configuration, $term, $like): void {
                $where
                    ->whereRaw('t.search_vector @@ websearch_to_tsquery(?, ?)', [$configuration, $term])
                    ->orWhereRaw('lower(t.title) like ?', [$like])
                    ->orWhereRaw('lower(t.body) like ?', [$like]);
            })
            ->orderByRaw("{$rank} desc", [$configuration, $term])
            ->orderByDesc('articles.updated_at')
            ->limit($limit)
            ->get();

        return $this->shape($rows, $locale);
    }

    /**
     * The portable path: substring containment, case-insensitively.
     *
     * @param  Builder<Article>  $query
     * @return list<array<string, mixed>>
     */
    private function byContainment(Builder $query, string $term, int $limit): array
    {
        $like = '%'.mb_strtolower($term).'%';

        $rows = $query
            ->join('article_translations as t', 't.article_id', '=', 'articles.id')
            ->select('articles.*', 't.locale as matched_locale', 't.title as matched_title')
            ->where(function (Builder $where) use ($like): void {
                $where
                    ->whereRaw('lower(t.title) like ?', [$like])
                    ->orWhereRaw('lower(t.body) like ?', [$like]);
            })
            ->orderByDesc('articles.updated_at')
            ->limit($limit)
            ->get();

        return $this->shape($rows, '');
    }

    /**
     * One row per ARTICLE, not per translation.
     *
     * A bilingual article matching in both languages is one answer, and
     * returning it twice would push a different answer off the panel.
     *
     * @param  \Illuminate\Support\Collection<int, Article>  $rows
     * @return list<array<string, mixed>>
     */
    private function shape(\Illuminate\Support\Collection $rows, string $preferred): array
    {
        $seen = [];

        foreach ($rows as $article) {
            $id = (string) $article->getKey();

            if (array_key_exists($id, $seen)) {
                continue;
            }

            $available = $article->translations->pluck('locale')->map(strval(...))->all();

            /*
             * Titled in the READER's language where it exists, not in the
             * language that happened to match. Somebody searching English for
             * a bilingual article should see the English title.
             */
            $served = in_array($preferred, $available, true)
                ? $preferred
                : (string) $article->default_locale;

            $title = $article->translations->firstWhere('locale', $served)?->title
                ?? $article->getAttribute('matched_title');

            $seen[$id] = [
                'id' => $id,
                'title' => $title,
                'type' => $article->type->value,
                'category_id' => (int) $article->category_id,
                'status' => $article->status->value,
                'internal_only' => (bool) $article->internal_only,
                'served_locale' => $served,
                'available_locales' => $available,
            ];
        }

        return array_values($seen);
    }
}
