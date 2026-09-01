<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\HealthController;
use App\Modules\Platform\Http\Middleware\IdempotencyKey;
use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Http\Controllers\AuthController;
use App\Modules\Security\Http\Controllers\DepartmentsController;
use App\Modules\Security\Http\Controllers\PasswordResetController;
use App\Modules\Security\Http\Controllers\ProfileController;
use App\Modules\Security\Http\Controllers\UsersController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
 * Every API route lives under /api/v1. The version segment is part of the
 * contract the generated TypeScript client is built from, so it is declared
 * once here rather than repeated per route.
 *
 * Authentication is Sanctum in SPA cookie mode: statefulApi() is registered in
 * bootstrap/app.php, so requests from the configured frontend origin carry the
 * session cookie. No token is ever issued.
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/healthz', [HealthController::class, 'show'])->name('platform.healthz');

    // Temporary write endpoint; see HealthController::echo().
    Route::post('/healthz-echo', [HealthController::class, 'echo'])->name('platform.healthz-echo');

    /*
     * ---------------------------------------------------------------------
     * Session operations — exempt from Idempotency-Key
     * ---------------------------------------------------------------------
     *
     * That middleware exists to stop a retried WRITE from creating a second
     * record. A session operation creates none, and a replayed sign-in response
     * cannot carry a Set-Cookie, which would make the replay actively wrong.
     * Requiring a key here would break every ordinary form post and curl for no
     * safety gain.
     *
     * The scope of this exemption is deliberately narrow — see the
     * administration group below, which does NOT get it.
     */
    Route::withoutMiddleware(IdempotencyKey::class)->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('auth.login');

        Route::post('/auth/password/forgot', [PasswordResetController::class, 'sendResetLink'])
            ->middleware('throttle:password-reset')
            ->name('auth.password.forgot');

        Route::post('/auth/password/reset', [PasswordResetController::class, 'reset'])
            // A separate budget from requesting a link — see the limiter.
            ->middleware('throttle:password-reset-confirm')
            ->name('auth.password.reset');

        Route::get('/auth/session', [AuthController::class, 'session'])->name('auth.session');

        Route::middleware('auth:web')->group(function (): void {
            Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

            Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('/profile/password', [ProfileController::class, 'changePassword'])
                ->name('profile.password');
        });
    });

    /*
     * ---------------------------------------------------------------------
     * Administration
     * ---------------------------------------------------------------------
     *
     * Every route carries an explicit capability. The middleware is the
     * enforcement — hiding a control in the UI is a suggestion, not a refusal,
     * and these endpoints are reachable by curl regardless.
     *
     * These are resource writes, so they DO honour Idempotency-Key: creating a
     * user twice because a request was retried is exactly the failure that
     * middleware prevents.
     */
    Route::middleware('auth:web')->group(function (): void {
        Route::middleware('can.capability:'.Capabilities::USER_MANAGE)->group(function (): void {
            Route::get('/users', [UsersController::class, 'index'])->name('users.index');
            Route::post('/users', [UsersController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [UsersController::class, 'show'])->name('users.show');
            Route::patch('/users/{user}', [UsersController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/deactivate', [UsersController::class, 'deactivate'])
                ->name('users.deactivate');
        });

        Route::middleware('can.capability:'.Capabilities::DEPARTMENT_MANAGE)->group(function (): void {
            Route::get('/departments', [DepartmentsController::class, 'index'])->name('departments.index');
            Route::post('/departments', [DepartmentsController::class, 'store'])->name('departments.store');
            Route::patch('/departments/{department}', [DepartmentsController::class, 'update'])
                ->name('departments.update');
            Route::post('/departments/{department}/deactivate', [DepartmentsController::class, 'deactivate'])
                ->name('departments.deactivate');
        });

        /*
         * Capability probes for surfaces whose real handlers arrive later.
         *
         * They exist so the refusal behaviour is enforced and TESTED from this
         * story onward rather than retrofitted — the guard is the deliverable
         * here, not the handler behind it.
         *
         * TODO(Story 2.4): replace with the audit log reader.
         * TODO(Story 2.3): replace with the settings surface.
         * TODO(Story 4.1): replace with the real ticket reassignment.
         */
        Route::get('/audit', fn () => new JsonResponse(['data' => []]))
            ->middleware('can.capability:'.Capabilities::AUDIT_READ)
            ->name('audit.index');

        Route::put('/settings/{key}', fn (string $key) => new JsonResponse(['key' => $key]))
            ->middleware('can.capability:'.Capabilities::SETTING_MANAGE)
            ->name('settings.update');

        Route::post('/tickets/{ticket}/reassign', fn (string $ticket) => new JsonResponse(['ticket' => $ticket]))
            ->middleware('can.capability:'.Capabilities::TICKET_REASSIGN)
            ->name('tickets.reassign');
    });
});
