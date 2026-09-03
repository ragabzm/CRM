<?php

declare(strict_types=1);

/*
 * Server-rendered artefacts only (emails, persisted notifications). On-screen
 * strings live in frontend/messages/{en,ar}.json. No key duplication across the
 * boundary.
 *
 * The split is not arbitrary: on-screen text has to change with the reader's
 * language at render time, while a notification is composed ONCE, in the
 * recipient's language, at the moment it is sent — and then stays that way
 * forever, because it is a record of something that was said to somebody.
 */

return [
    'assigned' => [
        'subject' => 'Ticket :reference has been assigned to you',
        'line' => ':actor assigned ":subject" to you.',
        'action' => 'Open the ticket',
    ],

    'customer_replied' => [
        'subject' => 'New reply on ticket :reference',
        'line' => 'The customer replied to ":subject".',
        'action' => 'Read the reply',
    ],

    'sla_at_risk' => [
        'subject' => 'Ticket :reference is close to its target',
        'line' => '":subject" has :minutes minutes left on its :timer target.',
        'action' => 'Open the ticket',
    ],

    'sla_breached' => [
        'subject' => 'Ticket :reference has missed its target',
        'line' => '":subject" missed its :timer target by :minutes minutes.',
        'action' => 'Open the ticket',
    ],

    'timer' => [
        'response' => 'first reply',
        'resolution' => 'resolution',
    ],

    'greeting' => 'Hello :name,',
    'signoff' => 'Ragab CRM',
];
