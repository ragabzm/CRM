<?php

use App\Modules\Platform\Http\Middleware\AssignRequestId;
use App\Modules\Security\Http\Middleware\EnsureActiveUser;
use App\Modules\Security\Http\Middleware\RequireCapability;
use App\Modules\Platform\Http\Middleware\IdempotencyKey;
use App\Modules\Platform\Http\ProblemDetailsHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Order is load-bearing. AssignRequestId must run first so that every
        // log line and every problem document — including ones produced while
        // the idempotency middleware is deciding — carries the correlation id.
        /*
         * Sanctum SPA cookie mode. Prepends EnsureFrontendRequestsAreStateful to
         * the api group, so requests from SANCTUM_STATEFUL_DOMAINS carry the
         * session cookie and CSRF token instead of a bearer token — which is what
         * keeps every credential out of reach of client JavaScript.
         */
        $middleware->statefulApi();

        $middleware->prependToGroup('api', AssignRequestId::class);
        $middleware->appendToGroup('api', IdempotencyKey::class);

        /*
         * Runs on every API request, after authentication has resolved a user.
         * Deactivation must bite on the NEXT request, not whenever the session
         * happens to expire.
         */
        $middleware->appendToGroup('api', EnsureActiveUser::class);

        // `can.capability:user.manage` on a route. Named to read as a sentence
        // and to not collide with Laravel's own `can:` gate middleware.
        $middleware->alias(['can.capability' => RequireCapability::class]);

        /*
         * Order: authenticate, check the account is live, authorize, and only
         * THEN do idempotency bookkeeping.
         *
         * Group middleware normally runs before route middleware, which put
         * IdempotencyKey ahead of the capability check — so a caller who was
         * never allowed to make the request was told "missing Idempotency-Key"
         * instead of "forbidden". A misleading error, and it had the idempotency
         * middleware reserving a key for work that was about to be refused.
         *
         * The framework defaults are restated because `priority()` replaces the
         * list wholesale; ours are appended at the end, nearest the controller.
         */
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
            EnsureActiveUser::class,
            RequireCapability::class,
            IdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The single shared handler. Every 4xx/5xx body in the system is built
        // here; controllers never write one (enforced by the architecture tests).
        $exceptions->render(function (Throwable $e, Request $request) {
            return app(ProblemDetailsHandler::class)($e, $request);
        });
    })->create();
