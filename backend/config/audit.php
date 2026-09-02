<?php

declare(strict_types=1);

return [
    /*
     * Best-effort revocation of UPDATE/DELETE on audit_entries at the database
     * role level, attempted once by the migration.
     *
     * The application already refuses to mutate an entry. This is the second
     * lock: it survives a bug in the application, a console command written in
     * a hurry, and anyone with a database client. It is best-effort because
     * hosted databases routinely deny GRANT OPTION to the application role —
     * failing there must not break a deploy.
     */
    'revoke_write_grants' => env('AUDIT_REVOKE_WRITE_GRANTS', true),

    'redaction' => [
        /*
         * Keys whose VALUES never reach the log.
         *
         * Config, not code, so adding a newly-invented credential-shaped key is
         * a one-line change rather than a deploy of the redactor. Matched
         * case-insensitively against the key at every depth.
         */
        'key_patterns' => [
            '/password/i',
            '/secret/i',
            '/token/i',
            '/api[_-]?key/i',
            '/authorization/i',
            '/cookie/i',
            '/credential/i',
        ],
        'placeholder' => '[REDACTED]',
    ],

    /*
     * Largest before/after payload stored per column. Anything bigger is
     * replaced by a marker recording its size: an audit row that cannot be
     * written is worse than one that records the shape of what happened.
     */
    'max_payload_bytes' => 65536,

    /*
     * Seeds a later pruning command. There is no retention job yet — the value
     * lives here so the policy is written down before anything depends on it.
     */
    'retention_days' => env('AUDIT_RETENTION_DAYS', 3650),
];
