<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Attachments\Application\SafeContentType;
use App\Modules\Platform\Attachments\Domain\Attachment;
use App\Modules\Platform\Attachments\Infrastructure\StorageSignedUrlIssuer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the bytes when the storage driver cannot sign a URL of its own.
 *
 * Only reached on a local disk — development, and the test suite. On S3 the
 * redirect goes to storage and this controller is never invoked, which is the
 * point: attachment bytes should not pass through the web process.
 *
 * The route is signed and expiring, so reaching this without a valid signature
 * is a 403 from the framework before any of this runs. It re-checks the scan
 * status regardless: a signature proves the link was issued, not that the file
 * is still safe to hand over.
 */
final class AttachmentStreamController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        $storedPath = base64_decode($path, true);

        /*
         * abort(), not a ProblemException.
         *
         * A browser follows the redirect here directly, so this route answers
         * a person rather than a client: problem+json is registered only for
         * api/* and JSON requests, and throwing one here would render as a 500
         * instead of the refusal it is.
         */
        if ($storedPath === false || $storedPath === '') {
            abort(404, 'That download link is not valid.');
        }

        /*
         * Re-checked, not trusted. A link issued five minutes ago for a file
         * that has since been quarantined again must not still work — and a
         * path is not proof of anything on its own.
         */
        $attachment = Attachment::query()->where('stored_path', $storedPath)->first();

        if ($attachment === null || ! $attachment->isDownloadable()) {
            abort(403, 'This file is not available for download.');
        }

        $disk = Storage::disk((string) config('attachments.disk'));

        return $disk->response(
            $storedPath,
            $attachment->filename,
            [
                'Content-Type' => SafeContentType::for($attachment->mime_type),
                'Content-Disposition' => StorageSignedUrlIssuer::contentDisposition($attachment->filename),
                // Belt and braces against a browser deciding for itself that
                // the bytes look like HTML.
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store, private',
            ],
        );
    }
}
