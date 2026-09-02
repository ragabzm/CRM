<?php

declare(strict_types=1);

return [
    /*
     * Which scanner backs the FileScanner port.
     *
     * `null` accepts everything and is what CI and local development run. It is
     * a deliberate, named choice rather than an accident: a test suite that
     * needed a virus daemon would be a test suite nobody runs, and the
     * interesting behaviour — quarantine, the state machine, the download gate
     * — is identical whichever scanner answers.
     */
    'driver' => env('SCANNER_DRIVER', 'null'),

    'clamav' => [
        'socket' => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 5),
        /*
         * Chunk size for the INSTREAM protocol. clamd rejects chunks larger
         * than StreamMaxLength; 8 KiB is well under every default.
         */
        'chunk_bytes' => (int) env('CLAMAV_CHUNK_BYTES', 8192),
    ],
];
