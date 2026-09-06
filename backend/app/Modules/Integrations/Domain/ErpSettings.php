<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain;

use App\Modules\Platform\Support\Settings\SettingsRegistry;

/**
 * The whole configuration surface of the adapter, in one place.
 *
 * Written out as constants rather than composed from a name, so
 * `SettingsHaveAReaderTest` can see that each one is read — a key built by
 * concatenation is a key no reader can grep for and no guard can check.
 */
final class ErpSettings
{
    public const ENABLED = 'integrations.erp.enabled';

    public const ENDPOINT = 'integrations.erp.endpoint';

    /** Write-only, encrypted at rest, never returned by any endpoint. */
    public const CREDENTIAL = 'integrations.erp.credential';

    /** The header the credential is sent in. `Authorization` by default. */
    public const AUTH_HEADER = 'integrations.erp.auth_header';

    /** `import` · `export` · `both`. */
    public const DIRECTION = 'integrations.erp.direction';

    /** `scheduled` · `event`. */
    public const TRIGGER = 'integrations.erp.trigger';

    /** Their field name → ours. Validated at save against the fixed targets. */
    public const FIELD_MAP = 'integrations.erp.field_map';

    public const TIMEOUT_SECONDS = 'integrations.erp.timeout_seconds';

    public const RETENTION_DAYS = 'integrations.exchange_log.retention_days';

    /**
     * The only fields an import may write, and there is nothing above them.
     *
     * A MAPPED CONTACT BECOMES A CUSTOMER. There is no organisation, no
     * account and no company record to map to — FR-117 was cut, and a field
     * map row targeting one is how it grows back: first a column, then a
     * screen, then a permission model keyed on it.
     *
     * @var list<string>
     */
    public const TARGETS = ['full_name', 'email', 'phone', 'preferred_locale', 'branch_code'];

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::ENABLED);
    }

    public function endpoint(): string
    {
        return trim((string) $this->settings->get(self::ENDPOINT));
    }

    public function credential(): string
    {
        return (string) $this->settings->get(self::CREDENTIAL);
    }

    public function authHeader(): string
    {
        $header = trim((string) $this->settings->get(self::AUTH_HEADER));

        return $header === '' ? 'Authorization' : $header;
    }

    public function direction(): string
    {
        return (string) $this->settings->get(self::DIRECTION);
    }

    public function trigger(): string
    {
        return (string) $this->settings->get(self::TRIGGER);
    }

    /** @return array<string, string> their field => ours */
    public function fieldMap(): array
    {
        $map = $this->settings->get(self::FIELD_MAP);

        return is_array($map) ? array_map(strval(...), $map) : [];
    }

    public function timeoutSeconds(): int
    {
        return max(1, (int) $this->settings->get(self::TIMEOUT_SECONDS));
    }

    public function retentionDays(): int
    {
        return max(1, (int) $this->settings->get(self::RETENTION_DAYS));
    }

    /**
     * The credential values this deployment holds, for the redactor.
     *
     * @return list<string>
     */
    public function secrets(): array
    {
        return array_values(array_filter([$this->credential()], static fn (string $v): bool => trim($v) !== ''));
    }
}
