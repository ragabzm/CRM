<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\HealthController;
use App\Modules\Platform\Http\Middleware\IdempotencyKey;
use App\Modules\Security\Http\Controllers\AuthController;
use App\Modules\Security\Http\Controllers\PasswordResetController;
use App\Modules\Security\Http\Controllers\ProfileController;
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
     * Authentication
     * ---------------------------------------------------------------------
     *
     * These POSTs are exempt from the Idempotency-Key requirement. That
     * middleware exists to stop a retried WRITE from creating a second record;
     * a session operation creates no record, and a replayed sign-in response
     * would be actively wrong because the replay cannot carry a Set-Cookie.
     * Requiring a key here would also break every ordinary form post and curl
     * for no safety gain.
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
});
