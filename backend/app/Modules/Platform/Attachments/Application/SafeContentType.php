<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Application;

/**
 * Decides what Content-Type a file may be served as.
 *
 * Even a file that passed the virus scan can be dangerous to hand back with its
 * own content type. An HTML or SVG document served from a domain the user
 * trusts runs its script in that origin — a stored XSS delivered by the
 * download endpoint, with a clean bill of health from the scanner, because the
 * file contains no virus at all.
 *
 * Anything on this list becomes application/octet-stream, which browsers save
 * rather than render.
 */
final class SafeContentType
{
    /**
     * Types that execute, or that a browser can be talked into executing.
     *
     * SVG is on the list because it is an image everywhere except in a browser,
     * where it is a document that can carry <script>.
     */
    private const NEVER_INLINE = [
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'application/xml',
        'text/xml',
        'application/pdf',
    ];

    public const FALLBACK = 'application/octet-stream';

    public static function for(string $sniffed): string
    {
        $type = strtolower(trim(explode(';', $sniffed)[0]));

        if ($type === '') {
            return self::FALLBACK;
        }

        foreach (self::NEVER_INLINE as $dangerous) {
            if ($type === $dangerous) {
                return self::FALLBACK;
            }
        }

        // Anything scriptable by name — application/javascript,
        // text/javascript, application/ecmascript, x-shellscript.
        if (str_contains($type, 'script')) {
            return self::FALLBACK;
        }

        return $type;
    }
}
