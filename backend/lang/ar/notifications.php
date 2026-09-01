<?php

declare(strict_types=1);

/*
 * Server-rendered artefacts only (emails, persisted notifications). On-screen
 * strings live in frontend/messages/{en,ar}.json. No key duplication across the
 * boundary.
 *
 * The split is not arbitrary: on-screen text has to change with the reader's
 * language at render time, while an email is composed once, in the recipient's
 * language, on the server. Two audiences, two lifetimes, two files.
 *
 * TODO: no automated check enforces the no-duplication rule yet — worth an
 * architecture test once either side has real keys.
 */

return [];
