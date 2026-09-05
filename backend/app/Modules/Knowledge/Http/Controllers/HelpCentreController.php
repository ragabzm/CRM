<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\ArticleCategory;
use App\Modules\Knowledge\Domain\Search\ArticleSearch;
use App\Modules\Knowledge\Http\Resources\ArticleResource;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The customer's help centre.
 *
 * Its own controller, its own query and its own serialiser — NOT the staff one
 * with fields stripped afterwards. Every field a customer must not see is a
 * field this class never fetches, so a column added to `articles` tomorrow
 * cannot appear here by forgetting to strip it.
 *
 * The exclusion is at the QUERY. Asking for an internal article by its exact
 * id gets a 404, identical to asking for one that does not exist: a 403 would
 * confirm the article is real, and "there is an internal article about your
 * problem that you may not read" is itself information.
 */
final class HelpCentreController extends Controller
{
    public function __construct(private readonly ArticleSearch $search) {}

    /**
     * Search first, categories second.
     *
     * @response array{data: array<int, array<string, mixed>>, categories: array<int, array<string, mixed>>}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'category_id' => ['sometimes', 'integer'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));
        $locale = app()->getLocale();

        $articles = $term !== ''
            ? $this->search->search($term, $locale, customerVisibleOnly: true)
            : $this->browse($locale, isset($validated['category_id']) ? (int) $validated['category_id'] : null);

        return new JsonResponse([
            'data' => $articles,

            /*
             * The fallback, always sent. Search is the surface and browsing is
             * what somebody does when their words did not work — offering it
             * only after an empty result means building the dead end first and
             * the way out second.
             */
            'categories' => $this->categories($locale),
        ]);
    }

    /**
     * One article, if a customer may read it.
     *
     * @response array<string, mixed>
     */
    public function show(string $article): JsonResponse
    {
        $found = Article::query()
            ->whereKey($article)
            ->tap(Article::scopeCustomerVisible(...))
            ->with('translations')
            ->first();

        if ($found === null) {
            /*
             * 404 for internal, for draft, for archived and for nonexistent —
             * one answer, so none of them is distinguishable from the others.
             *
             * A link to an article since archived lands here. The message says
             * the article is no longer available rather than showing a body
             * that was withdrawn on purpose.
             */
            throw ProblemException::make(
                'knowledge.article_unavailable',
                'That article is not available',
                404,
                'It may have been withdrawn, or it may never have been published. Search for something else, or send us a message.',
            );
        }

        $available = $found->translations->pluck('locale')->map(strval(...))->all();

        $served = ArticleResource::resolveLocale(
            $available,
            app()->getLocale(),
            (string) $found->default_locale,
        );

        $translation = $found->translations->firstWhere('locale', $served);

        /*
         * Built by hand, not by serialising the model. `internal_only`,
         * `has_been_published`, `published_by` and every other staff-side
         * fact is absent because it is never fetched into this array — not
         * because somebody remembered to remove it.
         */
        return new JsonResponse([
            'id' => (string) $found->getKey(),
            'title' => $translation?->title,
            'body' => $translation?->body,
            'category_id' => (int) $found->category_id,
            'served_locale' => $served,
            'available_locales' => $available,
            'updated_at' => $found->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function browse(string $locale, ?int $categoryId): array
    {
        $query = Article::query()->tap(Article::scopeCustomerVisible(...))->with('translations');

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return $query
            ->orderByDesc('updated_at')
            ->limit(ArticleSearch::MAX_LIMIT)
            ->get()
            ->map(function (Article $article) use ($locale): array {
                $available = $article->translations->pluck('locale')->map(strval(...))->all();
                $served = ArticleResource::resolveLocale($available, $locale, (string) $article->default_locale);

                return [
                    'id' => (string) $article->getKey(),
                    'title' => $article->translations->firstWhere('locale', $served)?->title,
                    'category_id' => (int) $article->category_id,
                    'served_locale' => $served,
                    'available_locales' => $available,
                ];
            })
            ->all();
    }

    /**
     * Only the categories that have something in them for a customer.
     *
     * A help centre listing eight categories where six open on nothing is a
     * help centre that looks broken.
     *
     * @return list<array<string, mixed>>
     */
    private function categories(string $locale): array
    {
        $withArticles = Article::query()
            ->tap(Article::scopeCustomerVisible(...))
            ->distinct()
            ->pluck('category_id')
            ->all();

        if ($withArticles === []) {
            return [];
        }

        return ArticleCategory::query()
            ->whereIn('id', $withArticles)
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (ArticleCategory $c): array => [
                'id' => (int) $c->getKey(),
                'name' => $locale === 'ar' ? $c->name_ar : $c->name_en,
            ])
            ->all();
    }
}
