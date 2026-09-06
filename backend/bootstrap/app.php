<?php

use App\Modules\Platform\Http\Middleware\AssignRequestId;
use App\Modules\Platform\Http\Middleware\SetLocaleFromRequest;
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

        /*
         * The chat widget, exempt from CSRF — deliberately, and narrowly.
         *
         * Sanctum's stateful mode CSRF-checks any request carrying a cookie.
         * The widget carries one, so every message it sent came back 419.
         *
         * The dance it would have to do is not available to it: it runs in an
         * iframe on a THIRD-PARTY site, so it is cross-site by construction,
         * and the story forbids it from holding the token anywhere JavaScript
         * can reach — which is what a CSRF header would require.
         *
         * What the exemption actually costs is small and worth naming. The
         * cookie is scoped to ONE conversation: it resolves to no user, holds
         * no capability, and the only endpoints that accept it append a
         * message to that conversation or close it. The worst a forged
         * cross-site request achieves is posting into the visitor's own
         * support chat — which is the endpoint's purpose. It cannot read a
         * ticket, reach another conversation, or touch anything a session
         * would.
         *
         * The real limits on where the widget runs are `frame-ancestors` on
         * the frame document and the embed allow-list, not this.
         */
        $middleware->validateCsrfTokens(except: ['api/v1/chat/*']);

        /*
         * Never redirect a guest — answer them.
         *
         * Laravel's Authenticate middleware, when a request does not ask for
         * JSON, resolves `route('login')` to build a redirect. This application
         * has no such route: it serves an API and the sign-in PAGE belongs to
         * the separate frontend deployment. The lookup therefore threw
         * RouteNotFoundException from inside the middleware — BEFORE the
         * AuthenticationException that the problem handler knows how to render
         * — and an unauthenticated caller got `500 platform.internal_error`
         * instead of `401 platform.unauthorized`.
         *
         * Returning null keeps the exception on the path the handler expects,
         * whatever the caller sent in Accept.
         */
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->prependToGroup('api', AssignRequestId::class);
        $middleware->appendToGroup('api', IdempotencyKey::class);

        /*
         * After the guards have run, so the fallback can read the signed-in
         * account's preference — and before any controller, so everything that
         * chooses wording sees the right locale.
         */
        $middleware->appendToGroup('api', SetLocaleFromRequest::class);

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
