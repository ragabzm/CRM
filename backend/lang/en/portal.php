<?php

declare(strict_types=1);

/*
 * Customer-facing server text. The word is REQUEST, never "ticket" — a ticket
 * is what the desk calls it internally, and a customer who raised a question
 * about their invoice did not file a ticket.
 */

return [
    'password_reset' => [
        'subject' => 'Reset your password',
        'greeting' => 'Hello,',
        'line' => 'We received a request to reset the password on your account.',
        'action' => 'Choose a new password',
        'expiry' => 'This link works for :minutes minutes and can only be used once.',
        'ignore' => 'If you did not ask for this, you can ignore this email — your password has not changed.',
        'signoff' => 'Ragab CRM',
    ],
];
