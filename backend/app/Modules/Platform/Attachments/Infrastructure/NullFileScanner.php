<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Infrastructure;

use App\Modules\Platform\Attachments\Domain\Scanning\FileScanner;
use App\Modules\Platform\Attachments\Domain\Scanning\ScanResult;

/**
 * Accepts everything. The default, and what CI runs.
 *
 * Named `Null` rather than `Always`/`Permissive` so nobody deploys it thinking
 * it scans. It exists because the behaviour worth testing — quarantine, the
 * state transitions, the download gate — is identical whichever scanner
 * answers, and a suite that required a virus daemon is a suite nobody runs.
 */
final class NullFileScanner implements FileScanner
{
    public function scan(string $absolutePath): ScanResult
    {
        return ScanResult::clean(['driver' => 'null']);
    }
}
