<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Application;

use DateTimeInterface;

/**
 * Issues a short-lived URL that hands over one file.
 *
 * A port because object storage and a developer's laptop answer it completely
 * differently: S3 signs a URL its own edge validates, while a local disk has no
 * edge at all and needs the application to serve the bytes behind a signed
 * route. Both must produce a link that expires and that forces a download.
 */
interface SignedUrlIssuer
{
    /**
     * @param  string  $storedPath  Path on the attachments disk.
     * @param  string  $filename  The name the browser should save it as.
     * @param  string  $contentType  Already coerced to something non-executable.
     */
    public function issue(
        string $storedPath,
        string $filename,
        string $contentType,
        DateTimeInterface $expiresAt,
    ): string;
}
