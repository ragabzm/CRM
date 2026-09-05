<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Knowledge\Domain\Article;
use App\Modules\Knowledge\Domain\ArticleTranslation;
use App\Modules\Knowledge\Domain\Enum\ArticleLocale;
use App\Modules\Knowledge\Domain\HtmlSanitiser;
use App\Modules\Knowledge\Http\Requests\UpsertArticleTranslationRequest;
use App\Modules\Knowledge\Http\Resources\ArticleResource;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;

/**
 * The words, one language at a time.
 *
 * A PUT per locale rather than one endpoint taking every language at once,
 * because an author works in one language at a time and an Arabic-only article
 * is a complete article. Sending both to save one would make an English draft
 * a required field of writing in Arabic.
 *
 * Every body goes through the sanitiser on the way in. That is the ONE door:
 * a body stored before a rule tightened cannot slip past a reader who arrives
 * after it, which is what a sanitise-on-read design would allow.
 */
final class ArticleTranslationsController extends Controller
{
    public function __construct(private readonly HtmlSanitiser $sanitiser) {}

    /**
     * @response array<string, mixed>
     */
    public function upsert(UpsertArticleTranslationRequest $request, Article $article, string $locale): JsonResponse
    {
        $this->assertKnownLocale($locale);

        $validated = $request->validated();
        $clean = $this->sanitiser->clean((string) $validated['body']);

        if ($this->sanitiser->isEmptyAfterCleaning($clean)) {
            /*
             * Refused rather than stored empty.
             *
             * Somebody who pasted a block of markup and got a blank article
             * back would have no idea their work was discarded, and would try
             * again exactly the same way. Saying so is the difference between
             * a rule and silent data loss.
             */
            throw ProblemException::make(
                'knowledge.body_empty_after_sanitising',
                'Nothing was left of that body',
                422,
                'Everything in it was removed by the content filter. '
                .'Formatting, lists, links and images are kept; scripts, embeds and styles are not.',
            );
        }

        ArticleTranslation::query()->updateOrCreate(
            ['article_id' => $article->getKey(), 'locale' => $locale],
            ['title' => trim((string) $validated['title']), 'body' => $clean],
        );

        return new JsonResponse(ArticleResource::toArray($article->refresh()->load('translations'), $locale));
    }

    /**
     * Removes one language version.
     *
     * Refused for the article's default language while others exist, because
     * the default is the fallback every reader lands on when theirs is
     * missing. Removing it turns "we do not have your language" into a blank
     * page.
     */
    public function destroy(Article $article, string $locale): JsonResponse
    {
        $this->assertKnownLocale($locale);

        $article->load('translations');

        if ((string) $article->default_locale === $locale) {
            throw ProblemException::make(
                'knowledge.default_translation_required',
                'This is the article’s default language',
                422,
                'Every reader whose own language is missing is shown this one. '
                .'Change the article’s default language first, then remove this version.',
                ['default_locale' => $locale],
            );
        }

        $translation = $article->translations->firstWhere('locale', $locale);

        if ($translation === null) {
            throw ProblemException::make(
                'knowledge.translation_not_found',
                'No such language version',
                404,
                "This article has no {$locale} version.",
            );
        }

        $translation->delete();

        return new JsonResponse(ArticleResource::toArray($article->refresh()->load('translations')));
    }

    private function assertKnownLocale(string $locale): void
    {
        if (ArticleLocale::tryFrom($locale) !== null) {
            return;
        }

        throw ProblemException::make(
            'knowledge.unknown_locale',
            'Unknown language',
            422,
            'Articles are written in '.implode(' or ', ArticleLocale::values()).'.',
        );
    }
}
