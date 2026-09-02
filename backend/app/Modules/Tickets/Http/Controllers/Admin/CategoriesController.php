<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Category;
use App\Modules\Tickets\Contracts\CategoryUsageProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The flat ticket category list.
 */
final class CategoriesController extends Controller
{
    public function __construct(private readonly CategoryUsageProbe $usage) {}

    /**
     * @response array{data: array<int, array{id:int,name:array{en:string,ar:string},sort_order:int}>}
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(fn (Category $c) => $this->shape($c))
            ->all();

        return new JsonResponse(['data' => $categories]);
    }

    /**
     * @response array{id:int,name:array{en:string,ar:string},sort_order:int}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $category = Category::create([
            'name_en' => trim($validated['name']['en']),
            'name_ar' => trim($validated['name']['ar']),
            'sort_order' => (int) (Category::query()->max('sort_order') ?? 0) + 1,
        ]);

        return new JsonResponse($this->shape($category), 201);
    }

    /**
     * @response array{id:int,name:array{en:string,ar:string},sort_order:int}
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate($this->rules($category->getKey()));

        $category->fill([
            'name_en' => trim($validated['name']['en']),
            'name_ar' => trim($validated['name']['ar']),
        ])->save();

        return new JsonResponse($this->shape($category->refresh()));
    }

    /**
     * Delete — refused while tickets still use it.
     *
     * @response array{deleted:int}
     */
    public function destroy(Category $category): JsonResponse
    {
        $inUse = $this->usage->activeTicketCount((int) $category->getKey());

        if ($inUse > 0) {
            /*
             * A refusal that names a COUNT and a PATH, not a generic "cannot
             * delete". The administrator's next question is always "which
             * ones?", and an answer they have to go and construct themselves is
             * how a rule turns into a support request.
             */
            throw ProblemException::make(
                'tickets.category_in_use',
                'Category is still in use',
                409,
                "Cannot delete: {$inUse} tickets still use this category. Reassign them first.",
                [
                    'count' => $inUse,
                    'path' => '/tickets?category='.$category->getKey(),
                ],
            );
        }

        $category->delete();

        return new JsonResponse(['deleted' => (int) $category->getKey()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            // No `parent` rule, because there is no parent. The list is flat by
            // construction — see the migration.
            'name.en' => [
                'required', 'string', 'min:1', 'max:120',
                Rule::unique('ticket_categories', 'name_en')->ignore($ignoreId),
            ],
            'name.ar' => [
                'required', 'string', 'min:1', 'max:120',
                Rule::unique('ticket_categories', 'name_ar')->ignore($ignoreId),
            ],
        ];
    }

    /**
     * @return array{id:int,name:array{en:string,ar:string},sort_order:int}
     */
    private function shape(Category $category): array
    {
        return [
            'id' => (int) $category->getKey(),
            'name' => ['en' => (string) $category->name_en, 'ar' => (string) $category->name_ar],
            'sort_order' => (int) $category->sort_order,
        ];
    }
}
