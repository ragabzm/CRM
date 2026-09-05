<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Exceptions;

use RuntimeException;

/**
 * Publishing an article with nothing to read in its own default language.
 *
 * The fallback path serves the default locale when the reader's is missing. An
 * article published without one has no fallback, so a reader whose language is
 * absent gets a blank page — which the story rules out explicitly.
 */
final class CannotPublishWithoutDefaultTranslation extends RuntimeException
{
    public static function forLocale(string $locale): self
    {
        return new self("This article has no {$locale} version, and {$locale} is its default language.");
    }
}
