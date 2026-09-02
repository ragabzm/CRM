<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Domain\Scanning;

use RuntimeException;

/**
 * The scanner could not be asked.
 *
 * Distinct from a failed scan on purpose. A file nobody could check stays in
 * quarantine and stays undownloadable — the same outward behaviour as a
 * pending scan — whereas treating it as clean would turn an outage into a
 * delivery mechanism, and treating it as failed would tell the customer their
 * perfectly good invoice contains a virus.
 */
final class ScannerUnreachable extends RuntimeException {}
