<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Domain;

/**
 * Where a file is in its journey from "uploaded" to "downloadable".
 *
 * Only Clean is downloadable, and that is checked at download time rather than
 * stored as a flag — one source of truth means the two cannot disagree.
 */
enum ScanStatus: string
{
    /** Uploaded, not yet judged. Also where an unreachable scanner leaves it. */
    case Pending = 'pending';

    case Clean = 'clean';

    case Failed = 'failed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
