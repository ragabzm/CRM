<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Resources;

use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\ArticleTranslation;

/**
 * What an article looks like on the wire.
 *
 * Two fields here are derived rather than stored, and both are derived in ONE
 * place for the same reason: `is_customer_visible` and `can_delete` are rules,
 * and a rule recomputed at each call site is a rule that eventually differs
 * between two of them. The interface renders what this says; it does not work
 * it out again.
 */
final class ArticleResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Article $article, ?string $servedLocale = null): array
    {
        /** @var list<ArticleTranslation> $translations */
        $translations = $article->relationLoaded('translations')
            ? $article->translations->all()
            : $article->translations()->get()->all();

        $available = array_values(array_map(
            static fn (ArticleTranslation $t): string => (string) $t->locale,
            $translations,
        ));

        return [
            'id' => (string) $article->getKey(),
            'type' => $article->type->value,
            'category_id' => (int) $article->category_id,
            'internal_only' => (bool) $article->internal_only,
            'status' => $article->status->value,
            'default_locale' => (string) $article->default_locale,

            /*
             * Which languages exist, so a list can show an availability chip
             * without fetching every body. An article that exists only in
             * Arabic is complete, not half-finished, and the chip is what says
             * so.
             */
            'available_locales' => $available,

            /*
             * The title, in whichever language the reader would be served.
             *
             * On the LIST too, not only on the record. An article list showing
             * identifiers is a list nobody can find anything in — and the
             * identifier is the one thing about an article that means nothing
             * to the person reading it.
             */
            'title' => self::titleFor($translations, self::resolveLocale(
                $available,
                app()->getLocale(),
                (string) $article->default_locale,
            )),

            /*
             * Never inferred by the caller from `status` and `internal_only`.
             * Both conditions, in one place — see `Article::isCustomerVisible`.
             */
            'is_customer_visible' => $article->isCustomerVisible(),

            /*
             * The interface offers Delete or Archive based on this, and the
             * server refuses on the same rule. Two answers computed once.
             */
            'can_delete' => $article->canBeDeleted(),

            'has_been_published' => (bool) $article->has_been_published,
            'published_at' => $article->published_at?->toIso8601String(),
            'published_by' => $article->published_by,
            'archived_at' => $article->archived_at?->toIso8601String(),
            'archived_by' => $article->archived_by,
            'created_at' => $article->created_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),

            ...($servedLocale === null ? [] : self::served($translations, $servedLocale)),
        ];
    }

    /**
     * The body a reader is actually given, and which language it is in.
     *
     * `served_locale` travels WITH the body rather than being left for the
     * reader to notice. Somebody who asked for Arabic and is looking at
     * English needs to be told, once, plainly — the alternative is a page that
     * looks like the site is broken, or worse, one they assume is the Arabic
     * version.
     *
     * @param  list<ArticleTranslation>  $translations
     * @return array<string, mixed>
     */
    private static function served(array $translations, string $servedLocale): array
    {
        $match = null;

        foreach ($translations as $translation) {
            if ((string) $translation->locale === $servedLocale) {
                $match = $translation;
                break;
            }
        }

        return [
            'served_locale' => $servedLocale,
            'title' => $match?->title,
            'body' => $match?->body,
            'translations' => array_map(static fn (ArticleTranslation $t): array => [
                'locale' => (string) $t->locale,
                'title' => (string) $t->title,
                'body' => (string) $t->body,
            ], $translations),
        ];
    }

    /**
     * The title in one language, or null when the article has no words yet.
     *
     * @param  list<ArticleTranslation>  $translations
     */
    private static function titleFor(array $translations, string $locale): ?string
    {
        foreach ($translations as $translation) {
            if ((string) $translation->locale === $locale) {
                return (string) $translation->title;
            }
        }

        return null;
    }

    /**
     * Which language to serve, given what the reader asked for.
     *
     * The reader's own if it exists, otherwise the article's default. Never
     * nothing: a blank page is the outcome this rule exists to prevent.
     *
     * @param  list<string>  $available
     */
    public static function resolveLocale(array $available, string $requested, string $default): string
    {
        return in_array($requested, $available, true) ? $requested : $default;
    }
}
