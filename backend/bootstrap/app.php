<?php

use App\Modules\Platform\Http\Middleware\AssignRequestId;
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
        $middleware->prependToGroup('api', AssignRequestId::class);
        $middleware->appendToGroup('api', IdempotencyKey::class);
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
