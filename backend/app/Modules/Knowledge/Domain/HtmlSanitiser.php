<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * What an article body is allowed to contain.
 *
 * An ALLOW-LIST, never a block-list. A block-list is a list of the attacks
 * somebody had thought of on the day they wrote it; everything invented since
 * passes. This starts from nothing and adds back the fourteen elements an
 * answer to a support question actually needs.
 *
 * This is half the defence. The other half is escaping at render, and both are
 * required — one of the two will be bypassed eventually, and the other is why
 * nothing happens when it is.
 *
 * Two things it does beyond stripping tags:
 *
 *   Every link is forced to `rel="noopener noreferrer"`. Without `noopener` a
 *   page opened from here can reach back through `window.opener` and navigate
 *   the tab it came from, which is a convincing way to put a fake sign-in page
 *   in front of an agent.
 *
 *   Only `http`, `https` and `mailto` survive on a link, and only `http`,
 *   `https` and `data:image` on an image. `javascript:` in an href is the
 *   oldest injection there is, and it is not a tag, so tag filtering alone
 *   never sees it.
 */
final class HtmlSanitiser
{
    /**
     * The elements an answer needs, and their attributes.
     *
     * `dir` is allowed on every block element on purpose: an Arabic article
     * quoting an English error message needs to mark the direction of the
     * quote, and stripping it would render the quote backwards.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'p' => ['dir'],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        'ol' => ['dir'],
        'ul' => ['dir'],
        'li' => ['dir'],
        'h2' => ['dir'],
        'h3' => ['dir'],
        'h4' => ['dir'],
        'blockquote' => ['dir'],
        'code' => [],
        'pre' => ['dir'],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https', 'data'])
            // Forced on every anchor, whatever the author wrote.
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        foreach (self::ALLOWED as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(string $html): string
    {
        return trim($this->sanitizer->sanitize($html));
    }

    /**
     * Is there anything left worth storing?
     *
     * A body that sanitises to nothing is refused rather than saved empty:
     * somebody who pasted a block of markup and got a blank article back would
     * have no idea their work was discarded, and would try again the same way.
     */
    public function isEmptyAfterCleaning(string $cleaned): bool
    {
        return trim(html_entity_decode(strip_tags($cleaned))) === ''
            && ! str_contains($cleaned, '<img');
    }
}
