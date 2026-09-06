<?php

declare(strict_types=1);

/*
 * Server-rendered artefacts only. On-screen strings live in
 * frontend/messages/{en,ar}.json — see the note in lang/en/emails.php for why
 * the two are split.
 *
 * What lands here is text the SERVER writes into the record: a note on a
 * ticket, composed once, in the language of whoever will read it back. It
 * stays that way afterwards, because it is a description of something that
 * happened rather than a label on a screen.
 */

return [
    'chat' => [
        'handing_off' => 'Let me get a person for you — someone will be with you shortly.',
        'answered_from' => 'Answered from our help centre:',
        'abandoned_note' => 'The visitor left the chat without closing it. The conversation above is the whole transcript.',
    ],
];
