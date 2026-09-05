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
    /*
     * Articles, their categories and their lifecycle.
     *
     * T2, beside Customers rather than above Tickets, because it needs nothing
     * from either: an article about refunds and a ticket about refunds are
     * related by MEANING, not by a foreign key. It reads Platform for
     * attachments and audit, and Security for capabilities, and that is all.
     * Putting it higher would invite the coupling the story rules out.
     */
    'Knowledge' => 2,
    'Tickets'   => 3,
    'Sla'       => 4,
    'Portal'    => 4,
    /*
     * The intake spine every transport enters through.
     *
     * At T4 rather than beside Email, because Email CALLS it: the mail webhook
     * hands its payload to `InboundIntake` and `MailChannelAdapter` implements
     * the Channels contract. A same-tier edge is exactly what deptrac.yaml
     * refuses, so Email moved down a tier instead — the only module that needs
     * to reach Channels, and the only one that moved.
     */
    'Channels'  => 4,
    'Email'     => 5,
];
