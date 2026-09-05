<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\Enum\ArticleStatus;
use App\Modules\Knowledge\Domain\Lifecycle\ArticleLifecycle;
use App\Modules\Knowledge\Exceptions\CannotPublishWithoutDefaultTranslation;
use App\Modules\Knowledge\Http\Resources\ArticleResource;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Publish and archive: the two decisions, each its own endpoint.
 *
 * Not a `status` field on update. Publishing is irreversible in the one way
 * that matters — the article can never be deleted afterwards — and a
 * transition reachable by PATCHing a field is a transition somebody reaches by
 * sending a whole object back with one thing changed.
 *
 * There is no third endpoint. There is no submit-for-review, because there is
 * no reviewer and no queue: a state articles enter and never leave is worse
 * than no state at all.
 */
final class ArticleLifecycleController extends Controller
{
    public function __construct(
        private readonly ArticleLifecycle $lifecycle,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * Draft or Archived → Published.
     *
     * @response array<string, mixed>
     */
    public function publish(Request $request, Article $article): JsonResponse
    {
        $article->load('translations');

        $available = $article->translations->pluck('locale')->map(strval(...))->all();

        if ($available === []) {
            throw ProblemException::make(
                'knowledge.nothing_to_publish',
                'This article has nothing to read',
                422,
                'Write at least one language version before publishing it.',
            );
        }

        $before = $article->status->value;

        try {
            $this->lifecycle->publish($article, (string) $request->user()?->getAuthIdentifier(), $available);
        } catch (CannotPublishWithoutDefaultTranslation $e) {
            /*
             * The article's own default language must exist, because it is the
             * fallback: a reader whose language is missing is served the
             * default, and an article published without one would hand them a
             * blank page.
             */
            throw ProblemException::make(
                'knowledge.default_translation_missing',
                'The default language version is missing',
                422,
                $e->getMessage().' Write it, or change the article’s default language to one you have.',
                ['default_locale' => (string) $article->default_locale, 'available' => $available],
            );
        }

        $this->audit->record(
            AuditAction::ArticlePublished,
            targetType: 'article',
            targetId: (string) $article->getKey(),
            before: ['status' => $before],
            after: ['status' => ArticleStatus::Published->value],
            actorId: (string) $request->user()?->getAuthIdentifier(),
        );

        return new JsonResponse(ArticleResource::toArray($article->refresh()));
    }

    /**
     * Published → Archived.
     *
     * @response array<string, mixed>
     */
    public function archive(Request $request, Article $article): JsonResponse
    {
        $before = $article->status->value;

        $this->lifecycle->archive($article, (string) $request->user()?->getAuthIdentifier());

        $this->audit->record(
            AuditAction::ArticleArchived,
            targetType: 'article',
            targetId: (string) $article->getKey(),
            before: ['status' => $before],
            after: ['status' => ArticleStatus::Archived->value],
            actorId: (string) $request->user()?->getAuthIdentifier(),
        );

        return new JsonResponse(ArticleResource::toArray($article->refresh()));
    }
}
