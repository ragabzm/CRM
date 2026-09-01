<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Settings;

/**
 * Implemented by any module service provider that owns settings.
 *
 * The registry does not know which modules exist — it walks the providers the
 * container already booted and asks the ones that implement this. That keeps
 * Platform (T0) from importing a single higher-tier module while still letting
 * every module declare its own knobs next to the code that reads them.
 */
interface RegistersSettings
{
    public function registerSettings(SettingsRegistry $registry): void;
}
