<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Infrastructure;

use App\Modules\Platform\Attachments\Application\SignedUrlIssuer;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Signs with the storage driver where it can, and with the application where it
 * cannot.
 *
 * S3 and its compatibles sign a URL their own edge validates, so the bytes
 * never pass through the application. A local disk has no edge; there the
 * application signs a route of its own and streams the file itself.
 *
 * The local path is a development convenience, not a second design: it is
 * slower and it puts attachment bytes through the web process, which is exactly
 * what production must not do. Both paths expire, and both force a download.
 */
final class StorageSignedUrlIssuer implements SignedUrlIssuer
{
    public function __construct(private readonly string $disk) {}

    public function issue(
        string $storedPath,
        string $filename,
        string $contentType,
        DateTimeInterface $expiresAt,
    ): string {
        $adapter = Storage::disk($this->disk);

        /*
         * Keyed off the DRIVER, not off providesTemporaryUrls().
         *
         * A local disk has no edge that could validate a signature, so a URL it
         * "provides" points at a path nothing serves. Laravel's fake disk
         * cheerfully claims the capability, which would let a test pass against
         * a link no browser could follow.
         */
        $isLocal = config("filesystems.disks.{$this->disk}.driver") === 'local';

        if (! $isLocal && $adapter->providesTemporaryUrls()) {
            return $adapter->temporaryUrl($storedPath, $expiresAt, [
                // RFC 5987, so a non-Latin filename survives the round trip
                // instead of arriving as a row of question marks.
                'ResponseContentDisposition' => self::contentDisposition($filename),
                'ResponseContentType' => $contentType,
            ]);
        }

        return URL::temporarySignedRoute('attachments.stream', $expiresAt, [
            'path' => base64_encode($storedPath),
        ]);
    }

    /**
     * `filename` for the ASCII fallback and `filename*` for the real one.
     *
     * Both, because a client that understands neither would otherwise save the
     * file under a name made of percent signs.
     */
    public static function contentDisposition(string $filename): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'download';
        $ascii = str_replace(['"', '\\'], '_', $ascii);

        return sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            $ascii,
            rawurlencode($filename),
        );
    }

    public static function assertConfigured(string $disk): void
    {
        if (config("filesystems.disks.{$disk}") === null) {
            throw new RuntimeException("The [{$disk}] disk is not configured.");
        }
    }
}
