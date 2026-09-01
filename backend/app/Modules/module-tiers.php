<?php

declare(strict_types=1);

/*
 * The single source of truth for module tiering.
 *
 * A module may depend only on modules in a STRICTLY LOWER tier. Modules that
 * share a tier (Sla, Email, Portal at T4) must never reference each other.
 * Enforcement lives in backend/deptrac.yaml and the tests under
 * tests/Architecture/; this file is what those tests and any future tooling
 * read so the ordering is declared exactly once.
 */

return [
    'Platform'  => 0,
    'Security'  => 1,
    'Customers' => 2,
    'Tickets'   => 3,
    'Sla'       => 4,
    'Email'     => 4,
    'Portal'    => 4,
];
