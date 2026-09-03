<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Platform\Console\Commands\OpenApiCheckCommand;
use App\Modules\Platform\Console\Commands\OpenApiGenerateCommand;
use App\Modules\Platform\Console\Commands\DatabaseCountsCommand;
use App\Modules\Platform\Console\Commands\PruneIdempotencyKeysCommand;
use App\Modules\Platform\Http\ProblemDetailsHandler;
use App\Modules\Platform\Attachments\Application\AttachmentSettings;
use App\Modules\Platform\Attachments\Application\AttachmentUploader;
use App\Modules\Platform\Attachments\Application\SignedUrlIssuer;
use App\Modules\Platform\Attachments\Domain\Scanning\FileScanner;
use App\Modules\Platform\Attachments\Infrastructure\ClamavFileScanner;
use App\Modules\Platform\Attachments\Infrastructure\NullFileScanner;
use App\Modules\Platform\Attachments\Infrastructure\StorageSignedUrlIssuer;
use App\Modules\Platform\Audit\Application\AuditQuery;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditRedactor;
use App\Modules\Platform\Support\Audit\AuditLogger;
use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingType;
use App\Modules\Platform\Support\Settings\SettingsCache;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Platform\Support\Settings\SettingsRepository;
use App\Modules\Platform\Support\RequestContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * T0. Owns the cross-cutting HTTP, logging and idempotency machinery every
 * other module inherits.
 */
final class PlatformServiceProvider extends ServiceProvider implements RegistersSettings
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/problem-details.php', 'problem-details');

        // One instance per request/process: the middleware writes to it and the
        // log processor and exception handler read from it.
        $this->app->singleton(RequestContext::class);
        $this->app->singleton(ProblemDetailsHandler::class);

        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(SettingsCache::class);
        /*
         * Definitions are collected inside the factory, not in a booted() hook.
         *
         * A hook populates only the instance that existed when it fired: drop
         * that singleton — as a test or a queue worker between jobs might —
         * and the next resolution comes back with an empty registry and every
         * key "unregistered". Collecting here means any resolution, at any
         * time, gets a complete registry.
         */
        $this->app->singleton(SettingsRegistry::class, function ($app): SettingsRegistry {
            $registry = new SettingsRegistry(
                $app->make(SettingsRepository::class),
                $app->make(SettingsCache::class),
                $app->make(AuditLogger::class),
            );

            $this->collectModuleSettings($registry);

            return $registry;
        });

        $this->app->singleton(AuditRedactor::class, fn (): AuditRedactor => AuditRedactor::fromConfig());

        $this->app->singleton(AuditWriter::class, fn ($app): AuditWriter => new AuditWriter(
            $app->make(RequestContext::class),
            $app->make(AuditRedactor::class),
            (int) config('audit.max_payload_bytes', 65536),
        ));

        /*
         * The seam Story 2.3 wrote its call sites against, now backed by the
         * table. Those controllers did not change: they were written against
         * this interface precisely so that this story could be one line.
         *
         * NullAuditLogger is kept, not deleted — it is what a test binds when
         * it wants to exercise a code path without asserting on audit rows.
         */
        $this->app->singleton(AuditLogger::class, AuditWriter::class);

        $this->app->singleton(AuditQuery::class);

        $this->app->singleton(AttachmentSettings::class);
        $this->app->singleton(AttachmentUploader::class);

        $this->app->singleton(
            SignedUrlIssuer::class,
            fn (): SignedUrlIssuer => new StorageSignedUrlIssuer((string) config('attachments.disk')),
        );

        /*
         * The scanner, chosen by config rather than by environment checks
         * scattered through the code. `null` is the default and what CI runs;
         * a deployment that wants real scanning sets SCANNER_DRIVER=clamav.
         */
        $this->app->singleton(FileScanner::class, function (): FileScanner {
            if (config('scanning.driver') === 'clamav') {
                return new ClamavFileScanner(
                    (string) config('scanning.clamav.socket'),
                    (int) config('scanning.clamav.timeout'),
                    (int) config('scanning.clamav.chunk_bytes'),
                );
            }

            return new NullFileScanner;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->registerActorResolver();

        if ($this->app->runningInConsole()) {
            $this->commands([
                OpenApiGenerateCommand::class,
                OpenApiCheckCommand::class,
                PruneIdempotencyKeysCommand::class,
                DatabaseCountsCommand::class,
            ]);

            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command(PruneIdempotencyKeysCommand::class)
                    ->dailyAt('03:10')
                    ->onOneServer();
            });
        }
    }

    /**
     * Asks every booted provider that owns settings to declare them.
     *
     * Platform is T0 and must not import a single higher-tier module, so it
     * cannot enumerate them by name. Instead it walks the providers the
     * container already has and asks the ones implementing this module's own
     * interface — the dependency points downward, and a new module needs no
     * change here to participate.
     */
    private function collectModuleSettings(SettingsRegistry $registry): void
    {
        foreach (array_keys($this->app->getLoadedProviders()) as $provider) {
            $instance = $this->app->getProvider($provider);

            if ($instance instanceof RegistersSettings) {
                $instance->registerSettings($registry);
            }
        }
    }

    /**
     * Platform's own settings.
     *
     * Each default repeats the value that used to live in config/, so nothing
     * changes behaviour on the day this ships — the value simply becomes
     * changeable without a redeployment.
     */
    public function registerSettings(SettingsRegistry $registry): void
    {
        $registry->register(new SettingDefinition(
            key: 'platform.attachments.allowed_mime_types',
            type: SettingType::Json,
            default: ['image/png', 'image/jpeg', 'application/pdf'],
            validator: static function (mixed $value): true|string {
                if (! is_array($value) || $value === []) {
                    return 'Provide at least one media type.';
                }

                foreach ($value as $mime) {
                    // An allow-list entry that is not a media type would either
                    // never match or, wildcarded, match everything.
                    if (! is_string($mime) || preg_match('#^[a-z]+/[a-z0-9.+-]+$#i', $mime) !== 1) {
                        return 'Each entry must be a media type such as image/png.';
                    }
                }

                return true;
            },
            summary: 'Media types an attachment may use.',
        ));

        $registry->register(new SettingDefinition(
            key: 'platform.attachments.max_bytes',
            type: SettingType::Int,
            default: 10_485_760,
            validator: static fn (mixed $value): true|string => is_int($value) && $value >= 1 && $value <= 104_857_600
                ? true
                : 'Must be between 1 byte and 100 MB.',
            summary: 'Largest attachment accepted, in bytes.',
        ));

        $registry->register(new SettingDefinition(
            key: 'platform.default_locale',
            type: SettingType::Enum,
            default: 'en',
            allowedValues: ['en', 'ar'],
            summary: 'Language used before a person chooses one.',
        ));
    }

    /**
     * Resolved lazily on every log line rather than snapshotted, because auth
     * runs after the request-id middleware that seeds the rest of the context.
     */
    private function registerActorResolver(): void
    {
        $context = $this->app->make(RequestContext::class);

        $context->setActorResolver(function () use ($context): array {
            // hasUser() avoids *triggering* authentication just to write a log
            // line, which would run the guard on every unauthenticated request.
            $guard = Auth::guard();
            $user = method_exists($guard, 'hasUser') && $guard->hasUser() ? $guard->user() : null;

            if ($user !== null) {
                return ['user', (string) $user->getAuthIdentifier()];
            }

            // No authenticated user. A missing correlation id means no request
            // is in flight, so this is the queue worker or the scheduler acting
            // on its own behalf rather than an anonymous caller. (Testing for
            // runningInConsole() here would misreport every simulated HTTP
            // request in the test suite as a service.)
            return $context->requestId() === null
                ? ['service', null]
                : ['guest', null];
        });

        /*
         * The same lazy trick for the display name the audit log denormalises.
         * Resolved on demand rather than snapshotted, because this runs before
         * auth:sanctum and there is no user yet at that point.
         */
        $context->setActorLabelResolver(function (): ?string {
            $guard = Auth::guard();
            $user = method_exists($guard, 'hasUser') && $guard->hasUser() ? $guard->user() : null;

            if ($user === null) {
                return null;
            }

            $name = $user->getAttribute('name');

            return is_string($name) && $name !== '' ? $name : null;
        });
    }
}
