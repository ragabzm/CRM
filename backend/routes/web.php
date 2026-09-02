<?php

use App\Modules\Platform\Attachments\Http\Controllers\AttachmentStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Serves an attachment when the storage driver cannot sign a URL of its own —
 * a local disk in development and in the test suite. On S3 the download
 * redirect points at storage and this route is never reached, which is the
 * arrangement production wants: attachment bytes should not travel through the
 * web process.
 *
 * On the WEB routes rather than the API ones, because a browser follows the
 * redirect here directly and this must return a file, not problem+json.
 *
 * The `signed` middleware is the authorisation: the link expires, and the
 * controller re-checks the scan status regardless — a signature proves the link
 * was issued, not that the file is still safe to hand over.
 */
Route::get('/attachments/stream/{path}', AttachmentStreamController::class)
    ->middleware('signed')
    ->name('attachments.stream');
