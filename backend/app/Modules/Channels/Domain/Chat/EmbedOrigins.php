<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use App\Modules\Platform\Support\Settings\SettingsRegistry;

/**
 * Where the widget may be embedded.
 *
 * A chat widget is a script anybody can copy off a page's source and paste
 * into their own site. Without this list, doing that gives them a working
 * support channel on our infrastructure, under our name, with our agents
 * answering — and the first anyone knows is when a customer complains about a
 * conversation nobody at this business had.
 *
 * So an unlisted origin gets NO TOKEN. Not a token that fails later, and not
 * an empty widget: the refusal happens before a conversation exists, which is
 * the only point at which it costs nothing.
 *
 * The portal is always allowed without being listed. It is the same
 * application; requiring an administrator to add their own site to the list
 * before chat worked on it would be a first-run failure that reads as a bug.
 */
final class EmbedOrigins
{
    public const SETTING = 'channels.chat.allowed_origins';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function allows(?string $origin): bool
    {
        $candidate = $this->normalise($origin);

        if ($candidate === null) {
            /*
             * No Origin header at all.
             *
             * Not a browser, or a same-origin navigation. Allowed, because the
             * portal's own widget arrives this way and because a missing header
             * proves nothing either direction — the allow-list is here to stop
             * a stranger's SITE using us, and a request with no site is not
             * that. The token it gets is still scoped to one conversation.
             */
            return true;
        }

        foreach ($this->allowed() as $allowed) {
            if ($allowed === $candidate) {
                return true;
            }
        }

        return false;
    }

    /**
     * The listed origins, plus this application's own.
     *
     * @return list<string>
     */
    public function allowed(): array
    {
        $configured = $this->settings->get(self::SETTING);

        $list = is_array($configured) ? $configured : [];

        $own = array_filter([
            $this->normalise((string) config('app.frontend_url')),
            $this->normalise((string) config('app.url')),
        ]);

        return array_values(array_unique(array_filter(array_merge(
            array_map(fn (mixed $o): ?string => $this->normalise(is_string($o) ? $o : null), $list),
            $own,
        ))));
    }

    /**
     * Scheme and host and port, lowercased — nothing else.
     *
     * A path or a trailing slash in the setting is the administrator writing
     * down a page rather than a site, and comparing the raw strings would
     * silently refuse the origin they meant.
     */
    private function normalise(?string $origin): ?string
    {
        if ($origin === null || trim($origin) === '') {
            return null;
        }

        $parts = parse_url(trim($origin));

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }
}
