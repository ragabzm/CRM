<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Platform\Console\Commands\OpenApiCheckCommand;
use App\Modules\Platform\Console\Commands\OpenApiGenerateCommand;
use App\Modules\Platform\Console\Commands\PruneIdempotencyKeysCommand;
use App\Modules\Platform\Http\ProblemDetailsHandler;
use App\Modules\Platform\Support\RequestContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * T0. Owns the cross-cutting HTTP, logging and idempotency machinery every
 * other module inherits.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/problem-details.php', 'problem-details');

        // One instance per request/process: the middleware writes to it and the
        // log processor and exception handler read from it.
        $this->app->singleton(RequestContext::class);
        $this->app->singleton(ProblemDetailsHandler::class);
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
    }
}
