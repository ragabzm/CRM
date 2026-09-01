<?php

declare(strict_types=1);

namespace Tests\Fixtures\ArchViolations\Modules\Sla\Domain;

use Tests\Fixtures\ArchViolations\Modules\Email\Domain\Thing;

/** T4 calling T4. Must be rejected by deptrac.yaml (per-module layers). */
final class CallsSameTier
{
    public function __construct(public readonly Thing $thing) {}
}
