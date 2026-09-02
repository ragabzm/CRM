<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Domain\Scanning;

/**
 * Asks something else whether a file is safe.
 *
 * A port, so the product does not depend on any particular scanner and the
 * quarantine state machine can be tested without one running.
 */
interface FileScanner
{
    /**
     * @param  string  $absolutePath  A readable path to the file on this host.
     *
     * @throws ScannerUnreachable when the scanner could not be reached or did
     *                            not answer. Never for a file it dislikes —
     *                            that is a ScanResult::failed.
     */
    public function scan(string $absolutePath): ScanResult;
}
