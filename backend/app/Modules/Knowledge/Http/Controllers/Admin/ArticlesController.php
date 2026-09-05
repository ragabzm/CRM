<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\Enum\ArticleStatus;
use App\Modules\Knowledge\Domain\Enum\ArticleType;
use App\Modules\Knowledge\Domain\Lifecycle\ArticleLifecycle;
use App\Modules\Knowledge\Exceptions\CannotDeletePublishedArticle;
use App\Modules\Knowledge\Http\Requests\StoreArticleRequest;
use App\Modules\Knowledge\Http\Requests\UpdateArticleRequest;
use App\Modules\Knowledge\Http\Resources\ArticleResource;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Articles: the list, the record, and the two ways one stops being current.
 *
 * The lifecycle transitions are their own endpoints rather than a field on
 * update, because they are decisions rather than edits — publishing puts an
 * answer in front of people and can never be fully undone, and an interface
 * that reached it by PATCHing a field would reach it by accident eventually.
 */
final class ArticlesController extends Controller
{
    public function __construct(
        private readonly ArticleLifecycle $lifecycle,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @response array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['sometimes', Rule::in(ArticleType::values())],
            'status' => ['sometimes', Rule::in(ArticleStatus::values())],
            'category_id' => ['sometimes', 'integer'],
            'internal_only' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Article::query()->with('translations');

        foreach (['type', 'status', 'category_id'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('internal_only', $filters)) {
            $query->where('internal_only', (bool) $filters['internal_only']);
        }

        if (($filters['q'] ?? '') !== '') {
            /*
             * Searches the TITLES, in every language the article has.
             *
             * Not the bodies: those are sanitised HTML, and matching a tag name
             * or an attribute value would return articles that do not mention
             * the word at all. Full body search is Story 8.2 and has its own
             * index.
             */
            $term = mb_strtolower(trim((string) $filters['q']));

            $query->whereHas('translations', function ($translations) use ($term): void {
                $translations->whereRaw('lower(title) like ?', ['%'.$term.'%']);
            });
        }

        $page = $query
            ->orderByDesc('updated_at')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return new JsonResponse([
            'data' => array_map(
                static fn (Article $a): array => ArticleResource::toArray($a),
                $page->items(),
            ),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * One article, in the reader's language where it exists.
     *
     * @response array<string, mixed>
     */
    public function show(Request $request, Article $article): JsonResponse
    {
        $article->load('translations');

        $available = $article->translations->pluck('locale')->map(strval(...))->all();

        $served = ArticleResource::resolveLocale(
            $available,
            // What the reader is reading the interface in, not what they last
            // stored on their account: the same rule every localised response
            // on this API follows.
            app()->getLocale(),
            (string) $article->default_locale,
        );

        return new JsonResponse(ArticleResource::toArray($article, $served));
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $article = new Article([
            'type' => $validated['type'],
            'category_id' => (int) $validated['category_id'],
            'default_locale' => $validated['default_locale'],
            'internal_only' => (bool) ($validated['internal_only'] ?? true),
            'created_by' => $this->actorId($request),
        ]);

        // Not fillable, and set here rather than by the request: a new article
        // is a draft, always, whatever the caller sent.
        $article->status = ArticleStatus::Draft;
        $article->has_been_published = false;
        $article->save();

        return new JsonResponse(ArticleResource::toArray($article), 201);
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $article->fill($request->validated())->save();

        return new JsonResponse(ArticleResource::toArray($article->refresh()));
    }

    /**
     * Delete — only for an article nobody has ever seen.
     */
    public function destroy(Request $request, Article $article): JsonResponse
    {
        try {
            $this->lifecycle->delete($article);
        } catch (CannotDeletePublishedArticle $e) {
            /*
             * 409 naming the alternative, not a bare refusal. "You cannot do
             * this" leaves somebody stuck; "archive it instead, because it has
             * been published" tells them what to do and why the rule exists.
             */
            throw ProblemException::make(
                'knowledge.archive_only',
                'Published articles cannot be deleted',
                409,
                $e->getMessage().' Archiving takes it out of circulation and keeps the record.',
                ['archive_path' => '/admin/knowledge/articles/'.$article->getKey().'/archive'],
            );
        }

        $this->audit->record(
            AuditAction::ArticleDeleted,
            targetType: 'article',
            targetId: (string) $article->getKey(),
            before: ['status' => $article->status->value],
            after: null,
            actorId: $this->actorId($request),
        );

        return new JsonResponse(['deleted' => (string) $article->getKey()]);
    }

    private function actorId(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id === null ? null : (string) $id;
    }
}
