<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Http;

use App\Modules\Platform\Attachments\Domain\Attachment;

/**
 * What an attachment looks like on the wire.
 *
 * One place, because one of the fields is a rule rather than a value:
 * `stored_path` is never published. Where the bytes live is not the client's
 * business, and publishing it invites someone to try it. A second controller
 * shaping its own response is a second chance to forget that, and the forgetting
 * would not show up in any test that checks the fields it DID include.
 *
 * It lives in Platform, with the model, so a module outside Platform can render
 * an attachment without importing Platform's model.
 */
final class AttachmentPresenter
{
    /**
     * Everything an authenticated caller may see.
     *
     * @return array<string, mixed>
     */
    public static function full(Attachment $attachment): array
    {
        return [
            'id' => (string) $attachment->getKey(),
            'owner_type' => $attachment->owner_type,
            'owner_id' => $attachment->owner_id,
            'filename' => $attachment->filename,
            'byte_size' => $attachment->byte_size,
            'mime_type' => $attachment->mime_type,
            'uploader_id' => $attachment->uploader_id,
            'uploaded_at' => $attachment->uploaded_at?->toIso8601String(),
            'scan_status' => $attachment->scan_status,
            // The reason only, never the raw scanner output — that can contain
            // paths and signature databases nobody outside operations needs.
            'scan_reason' => is_array($attachment->scan_result)
                ? ($attachment->scan_result['reason'] ?? null)
                : null,
            'scanned_at' => $attachment->scanned_at?->toIso8601String(),
            // Derived from the status, so the two can never disagree.
            'downloadable' => $attachment->isDownloadable(),
        ];
    }

    /**
     * The little an anonymous uploader needs back.
     *
     * Enough to render the row they just added and its scan chip, and nothing
     * about who else it belongs to or where it went. A stranger uploading to a
     * public form has no business learning the shape of our records.
     *
     * @return array<string, mixed>
     */
    public static function summary(Attachment $attachment): array
    {
        return [
            'id' => (string) $attachment->getKey(),
            'filename' => $attachment->filename,
            'byte_size' => $attachment->byte_size,
            'mime_type' => $attachment->mime_type,
            'scan_status' => $attachment->scan_status,
            'downloadable' => $attachment->isDownloadable(),
        ];
    }
}
