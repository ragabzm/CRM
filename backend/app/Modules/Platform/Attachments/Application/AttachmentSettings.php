<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Application;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Throwable;

/**
 * The upload rules, as an administrator currently has them set.
 *
 * Read at VALIDATION time, never cached at boot. An administrator who tightens
 * the allow-list expects the next upload to obey it, not the next deploy — that
 * is the whole reason these are settings rather than constants.
 *
 * Falls back to config when a setting cannot be read at all. A registry outage
 * must not mean "accept everything": the fallback is the conservative default,
 * not an open door.
 */
final class AttachmentSettings
{
    public const ALLOWED_MIME_TYPES_KEY = 'platform.attachments.allowed_mime_types';

    public const MAX_BYTES_KEY = 'platform.attachments.max_bytes';

    public function __construct(private readonly SettingsRegistry $registry) {}

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        $configured = $this->read(self::ALLOWED_MIME_TYPES_KEY);

        if (! is_array($configured) || $configured === []) {
            /** @var list<string> $fallback */
            $fallback = config('attachments.defaults.allowed_mime_types', []);

            return $fallback;
        }

        return array_values(array_filter($configured, 'is_string'));
    }

    public function maxBytes(): int
    {
        $configured = $this->read(self::MAX_BYTES_KEY);

        return is_int($configured) && $configured > 0
            ? $configured
            : (int) config('attachments.defaults.max_bytes', 10485760);
    }

    private function read(string $key): mixed
    {
        try {
            return $this->registry->get($key);
        } catch (Throwable) {
            // A settings failure must not become "no limits".
            return null;
        }
    }
}
