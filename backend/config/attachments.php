<?php

declare(strict_types=1);

return [
    /*
     * The disk holding both prefixes. PRIVATE, with no URL mapping: an
     * attachment must never be reachable by guessing a path, and the only way
     * to read one is a short-lived signed URL issued after the scan passed.
     */
    'disk' => env('ATTACHMENTS_DISK', 'attachments'),

    'prefixes' => [
        'quarantine' => 'quarantine',
        'clean' => 'clean',
    ],

    /*
     * How long a download link lives.
     *
     * Five minutes is long enough to click and short enough that a URL pasted
     * into a chat thread is dead before anyone else opens it.
     */
    'signed_url_minutes' => (int) env('ATTACHMENTS_SIGNED_URL_MINUTES', 5),

    /*
     * Fallbacks used only when the settings registry has no row — a fresh
     * install, or a settings read that failed. The administrator-facing values
     * live in the registry (platform.attachments.*) and are read at validation
     * time, never cached at boot.
     */
    'defaults' => [
        'allowed_mime_types' => ['image/png', 'image/jpeg', 'application/pdf'],
        'max_bytes' => 10 * 1024 * 1024,
    ],
];
