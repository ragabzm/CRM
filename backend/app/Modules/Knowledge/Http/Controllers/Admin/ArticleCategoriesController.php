<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\ArticleCategory;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The flat article category list.
 *
 * Shaped like the ticket category endpoint on purpose — an administrator who
 * has organised one should not have to learn a second interface — but it is a
 * different list over a different table. See the migration for why.
 */
final class ArticleCategoriesController extends Controller
{
    /**
     * @response array{data: array<int, array{id:int,name:array{en:string,ar:string},sort_order:int,article_count:int}>}
     */
    public function index(): JsonResponse
    {
        /*
         * The counts come with the list, not from a call per row. They are what
         * an administrator scans for before deleting anything, and the refusal
         * below quotes the same number.
         */
        $counts = Article::query()
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categories = ArticleCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(fn (ArticleCategory $c) => $this->shape($c, (int) ($counts[$c->getKey()] ?? 0)))
            ->all();

        return new JsonResponse(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $category = ArticleCategory::create([
            'name_en' => trim($validated['name']['en']),
            'name_ar' => trim($validated['name']['ar']),
            'sort_order' => (int) (ArticleCategory::query()->max('sort_order') ?? 0) + 1,
        ]);

        return new JsonResponse($this->shape($category, 0), 201);
    }

    public function update(Request $request, ArticleCategory $category): JsonResponse
    {
        $validated = $request->validate($this->rules((int) $category->getKey()));

        $category->fill([
            'name_en' => trim($validated['name']['en']),
            'name_ar' => trim($validated['name']['ar']),
        ])->save();

        return new JsonResponse($this->shape($category->refresh(), $this->articleCount($category)));
    }

    /**
     * Delete — refused while articles still sit in it.
     *
     * Every article belongs to exactly one category, so deleting an occupied
     * one has no correct outcome: the articles cannot be left pointing at
     * nothing, and moving them somewhere the administrator did not choose is a
     * decision this endpoint has no business making.
     */
    public function destroy(ArticleCategory $category): JsonResponse
    {
        $inUse = $this->articleCount($category);

        if ($inUse > 0) {
            /*
             * A refusal naming a COUNT and a PATH, not a generic "cannot
             * delete". The next question is always "which ones?", and an
             * answer somebody has to construct themselves is how a rule turns
             * into a support request.
             */
            throw ProblemException::make(
                'knowledge.category_in_use',
                'Category is still in use',
                409,
                "Cannot delete: {$inUse} articles are filed under this category. Move them first.",
                [
                    'count' => $inUse,
                    'path' => '/admin/knowledge?category='.$category->getKey(),
                ],
            );
        }

        $category->delete();

        return new JsonResponse(['deleted' => (int) $category->getKey()]);
    }

    private function articleCount(ArticleCategory $category): int
    {
        return Article::query()->where('category_id', $category->getKey())->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            // No `parent` rule, because there is no parent. Flat by
            // construction — see the migration.
            'name.en' => [
                'required', 'string', 'min:1', 'max:120',
                Rule::unique('article_categories', 'name_en')->ignore($ignoreId),
            ],
            'name.ar' => [
                'required', 'string', 'min:1', 'max:120',
                Rule::unique('article_categories', 'name_ar')->ignore($ignoreId),
            ],
        ];
    }

    /**
     * @return array{id:int,name:array{en:string,ar:string},sort_order:int,article_count:int}
     */
    private function shape(ArticleCategory $category, int $articleCount): array
    {
        return [
            'id' => (int) $category->getKey(),
            'name' => ['en' => (string) $category->name_en, 'ar' => (string) $category->name_ar],
            'sort_order' => (int) $category->sort_order,
            'article_count' => $articleCount,
        ];
    }
}
