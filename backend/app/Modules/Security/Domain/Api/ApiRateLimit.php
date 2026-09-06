<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Api;

use App\Modules\Platform\Support\Settings\SettingsRegistry;

/**
 * How much one API client may ask of us in a minute.
 *
 * A tiny class rather than a `->get()` inside the service provider, and the
 * reason is not tidiness: a provider WIRES, it does not decide. A policy value
 * read where the wiring happens is a policy nobody can find, nobody can test
 * without booting the container, and — as `SettingsHaveAReaderTest` pointed
 * out — nobody can prove is read at all, because the only mention sits in the
 * file that declared it.
 */
final class ApiRateLimit
{
    public const SETTING = 'security.api.requests_per_minute';

    /** A floor, not a default: zero would refuse every request. */
    private const MINIMUM = 1;

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function perMinute(): int
    {
        return max(self::MINIMUM, (int) $this->settings->get(self::SETTING));
    }
}
