<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain\Lifecycle;

use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\Enum\ArticleStatus;
use App\Modules\Knowledge\Exceptions\CannotDeletePublishedArticle;
use App\Modules\Knowledge\Exceptions\CannotPublishWithoutDefaultTranslation;

/**
 * The three transitions, and the one rule that outlives all of them.
 *
 * `has_been_published` is set at the first publish and NEVER cleared. It is not
 * derived from `published_at`, which archiving and re-publishing both overwrite
 * — a flag computed from a timestamp that moves is a flag that eventually says
 * the wrong thing, and the thing it guards is permanent deletion.
 *
 * So: publish once, and this article can never be deleted, whatever happens to
 * it afterwards. Archive, re-publish, archive again — it is still an article
 * that was once in front of people.
 *
 * Pure transitions, no HTTP. The controller turns a refusal into a problem
 * document; a console command or an import would turn it into something else,
 * and neither should have to reimplement the rule to do it.
 */
final class ArticleLifecycle
{
    /**
     * Draft or Archived → Published.
     *
     * @param  list<string>  $availableLocales  Which translations exist.
     */
    public function publish(Article $article, string $actorId, array $availableLocales): void
    {
        if (! in_array($article->default_locale, $availableLocales, true)) {
            throw CannotPublishWithoutDefaultTranslation::forLocale($article->default_locale);
        }

        $article->status = ArticleStatus::Published;
        $article->published_at = now();
        $article->published_by = $actorId;

        /*
         * Cleared, because the article is no longer archived. The publish
         * timestamp above is overwritten on a second publish and that is
         * correct: "when did this go live" means the current life, not the
         * first one. What must not move is the flag below.
         */
        $article->archived_at = null;
        $article->archived_by = null;

        // Once true, always true. This is the line that makes delete refuse.
        $article->has_been_published = true;

        $article->save();
    }

    /** Published → Archived. */
    public function archive(Article $article, string $actorId): void
    {
        $article->status = ArticleStatus::Archived;
        $article->archived_at = now();
        $article->archived_by = $actorId;

        $article->save();
    }

    /**
     * Removes an article that nobody has ever seen.
     *
     * @throws CannotDeletePublishedArticle when it has been published, ever.
     */
    public function delete(Article $article): void
    {
        if ($article->has_been_published) {
            throw CannotDeletePublishedArticle::make();
        }

        $article->delete();
    }
}
