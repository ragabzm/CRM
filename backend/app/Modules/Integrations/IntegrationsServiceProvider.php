<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Modules\Integrations\Console\Commands\PruneExchangeLogCommand;
use App\Modules\Integrations\Contracts\ErpTransport;
use App\Modules\Integrations\Domain\ErpSettings;
use App\Modules\Integrations\Domain\ExchangeRetention;
use App\Modules\Integrations\Domain\Redactor;
use App\Modules\Integrations\Infrastructure\RestErpTransport;
use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Platform\Support\Settings\SettingType;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Wires one adapter, one log and one prune.
 *
 * Off by default, and the product is complete that way. Nothing in the
 * ticketing loop, the portal or the console asks whether this module is
 * configured — an ERP that has been unreachable since Tuesday is invisible
 * from every screen, which is what makes this epic optional in the way the
 * planning artefacts say it is.
 */
final class IntegrationsServiceProvider extends ServiceProvider implements RegistersSettings
{
    public function register(): void
    {
        /*
         * ONE adapter, bound to the port. A named vendor adapter would be a
         * second binding here, which is exactly the shape
         * `NoVendorErpClientTest` refuses.
         */
        $this->app->scoped(ErpTransport::class, RestErpTransport::class);

        // Who sweeps which rows. A singleton because the claims are made
        // during boot and read by the prune long afterwards.
        $this->app->singleton(ExchangeRetention::class);

        /*
         * The redactor is built with THIS DEPLOYMENT'S configured secrets, so
         * the log cannot be written without them being removable. Resolved per
         * request rather than as a singleton: a credential changed in the
         * console must take effect on the next exchange, not at the next
         * deploy.
         */
        $this->app->scoped(Redactor::class, static fn ($app): Redactor => new Redactor(
            $app->make(ErpSettings::class)->secrets(),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneExchangeLogCommand::class]);

            $this->app->booted(function (): void {
                /*
                 * Daily, and on one server. The retention policy is the only
                 * deletion path there is, so it has to actually run — a policy
                 * that depends on somebody remembering to invoke it is a log
                 * that grows for ever.
                 */
                $this->app->make(Schedule::class)
                    ->command(PruneExchangeLogCommand::class)
                    ->dailyAt('03:40')
                    ->onOneServer();
            });
        }
    }

    public function registerSettings(SettingsRegistry $registry): void
    {
        $registry->register(new SettingDefinition(
            key: ErpSettings::ENABLED,
            type: SettingType::Bool,
            default: false,
            summary: 'Whether the ERP integration runs at all. Off, the product is complete without it.',
        ));

        $registry->register(new SettingDefinition(
            key: ErpSettings::ENDPOINT,
            type: SettingType::String,
            default: '',
            validator: static fn (mixed $v): true|string => is_string($v) && (trim($v) === '' || preg_match('#^https://[^/\s]+#', trim($v)) === 1)
                ? true
                /*
                 * HTTPS only. A credential travelling to an ERP over plain
                 * HTTP is a credential on the wire, and "it is an internal
                 * network" is a claim nobody can check from here.
                 */
                : 'The endpoint must be an https:// URL.',
            summary: 'The base URL of the ERP.',
        ));

        $registry->register(new SettingDefinition(
            key: ErpSettings::CREDENTIAL,
            type: SettingType::String,
            default: '',
            // Write-only. Never echoed back, not even to the administrator who
            // set it: a value that can be read back leaks through a screen
            // share, and this one opens somebody else's system.
            secret: true,
            summary: 'The credential sent with every exchange. Write-only; it cannot be read back.',
        ));

        $registry->register(new SettingDefinition(
            key: ErpSettings::AUTH_HEADER,
            type: SettingType::String,
            default: 'Authorization',
            summary: 'The header the credential is sent in.',
        ));

        $registry->register(new SettingDefinition(
            key: ErpSettings::DIRECTION,
            type: SettingType::Enum,
            default: 'import',
            allowedValues: ['import', 'export', 'both'],
            summary: 'Whether records come in, go out, or both.',
        ));

        $registry->register(new SettingDefinition(
            key: ErpSettings::TRIGGER,
            type: SettingType::Enum,
            default: 'scheduled',
            allowedValues: ['scheduled', 'event'],
            summary: 'Whether exchanges run on a schedule or when something happens.',
        ));

        $registry->register(new SettingDefinition(
            key: ErpSettings::FIELD_MAP,
            type: SettingType::Json,
            default: [],
            validator: static function (mixed $value): true|string {
                if (! is_array($value)) {
                    return 'The field map is a set of their field name to ours.';
                }

                foreach ($value as $theirs => $ours) {
                    if (! is_string($theirs) || trim($theirs) === '') {
                        return 'Each row needs the name of a field in their system.';
                    }

                    if (! is_string($ours) || ! in_array($ours, ErpSettings::TARGETS, true)) {
                        /*
                         * REFUSED AT SAVE, not discovered at 3am in a failed
                         * job. And the fixed list is why there is no
                         * organisation row: a mapped contact becomes a
                         * customer and nothing maps above it, so a target
                         * naming a company is refused here rather than growing
                         * a table later.
                         */
                        return sprintf(
                            '[%s] is not something a customer has. Map to one of: %s.',
                            is_string($ours) ? $ours : gettype($ours),
                            implode(', ', ErpSettings::TARGETS),
                        );
                    }
                }

                return true;
            },
            summary: 'Which field in the ERP becomes which field on a customer.',
        ));

        $registry->register(new SettingDefinition(
            key: ErpSettings::TIMEOUT_SECONDS,
            type: SettingType::Int,
            default: 15,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 1 && $v <= 120
                ? true
                : 'The exchange timeout must be between 1 and 120 seconds.',
            summary: 'How long one exchange waits for the ERP before failing.',
        ));

        $registry->register(new SettingDefinition(
            key: ErpSettings::RETENTION_DAYS,
            type: SettingType::Int,
            default: 90,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 1 && $v <= 3650
                ? true
                : 'Exchange log retention must be between 1 and 3650 days.',
            summary: 'How long exchange log rows are kept. This is the only way one is ever deleted.',
        ));
    }
}
