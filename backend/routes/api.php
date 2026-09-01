<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
 * Every API route lives under /api/v1. The version segment is part of the
 * contract the generated TypeScript client is built from, so it is declared
 * once here rather than repeated per route.
 *
 * Sanctum is installed and its guard is wired (see config/sanctum.php), but no
 * authenticated endpoint ships in Story 1.1 — auth flows are a later story.
 * Keeping model-returning routes out for now also keeps OpenAPI generation
 * free of a database dependency, which is what lets the CI drift check run
 * without a Postgres service.
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/healthz', [HealthController::class, 'show'])->name('platform.healthz');

    // Temporary write endpoint; see HealthController::echo().
    Route::post('/healthz-echo', [HealthController::class, 'echo'])->name('platform.healthz-echo');
});
