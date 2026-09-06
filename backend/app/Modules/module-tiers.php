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
    /*
     * The AI connector, its sanitiser and its switches.
     *
     * T1, beside Security, because EVERY future consumer sits above it:
     * Knowledge (T2) wants suggested articles, Tickets (T3) wants a summary
     * and a proposed category, Channels (T4) wants the chatbot. A module they
     * all call has to be below all of them.
     *
     * It needs nothing from anybody except Platform's settings registry. That
     * is not an accident of the current scope — it is the shape that keeps AI
     * assistive: a connector that reached up into Tickets to fetch its own
     * context would be a connector the product could not run without.
     */
    'Ai'        => 1,
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
    /*
     * The three in-ticket assists.
     *
     * T4, because it reads BOTH Tickets (T3) for the conversation and
     * Knowledge (T2) for the article corpus — and neither of those two knows
     * the other exists. Putting the assists inside Tickets would have meant
     * teaching Tickets about Knowledge, which is the coupling Knowledge's own
     * tier note exists to prevent.
     *
     * It reads and never writes. Confirming a proposal is an ordinary ticket
     * change through the command path a person already uses; nothing here
     * holds a Tickets command, which is what "AI proposes, a person decides"
     * looks like in a dependency graph.
     */
    'Assist'    => 4,
    'Email'     => 5,
    /*
     * The fixed report set.
     *
     * T5, beside Email, because it READS the two modules underneath it:
     * Tickets for volume and Sla for how long things took. It owns no table,
     * writes nothing, and nothing depends on it — which is what lets it sit at
     * the top without anything else having to know it is there.
     *
     * Beside Email rather than above it because the two never speak. A report
     * about email volume would be a report reading a channel's own tables, and
     * the figures this story ships are about tickets.
     */
    'Reporting' => 5,
];
