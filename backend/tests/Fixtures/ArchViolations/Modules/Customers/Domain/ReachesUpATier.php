<?php

declare(strict_types=1);

namespace Tests\Fixtures\ArchViolations\Modules\Customers\Domain;

use Tests\Fixtures\ArchViolations\Modules\Tickets\Domain\Thing;

/** T2 reaching into T3. Must be rejected by both deptrac configs. */
final class ReachesUpATier
{
    public function __construct(public readonly Thing $thing) {}
}
