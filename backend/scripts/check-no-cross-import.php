#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Fails if any PHP source under backend/ reaches into the frontend application.
 *
 * The two applications are separately deployable, which only holds if neither
 * can see the other's files. The symmetrical scan is
 * frontend/scripts/check-no-cross-import.mjs.
 *
 * Usage:
 *   php scripts/check-no-cross-import.php              # scan the backend
 *   php scripts/check-no-cross-import.php --self-test  # prove the scan bites
 */

const SCANNED_DIRS = ['app', 'bootstrap', 'config', 'database', 'routes', 'scripts', 'tests'];

const SCANNED_EXTENSIONS = ['php'];

/**
 * This file necessarily contains the very strings it looks for (the patterns
 * themselves and the --self-test fixture), so it excludes itself. Nothing else
 * gets an exemption.
 */
const EXCLUDED_FILES = ['scripts/check-no-cross-import.php'];

/**
 * Only *paths into the frontend tree* are forbidden. A bare mention of the word
 * "frontend" (a config key, a comment, a CORS origin) is not a cross-import,
 * and flagging it would train people to ignore this check.
 */
const FORBIDDEN_PATTERNS = [
    '#(?:\.\./)+frontend/#',
    '#[\'"]frontend/(?:app|lib|components|scripts|__tests__)/#',
    '#\brequire(?:_once)?\s*\(?\s*[\'"][^\'"]*frontend/#',
    '#\binclude(?:_once)?\s*\(?\s*[\'"][^\'"]*frontend/#',
];

$root = dirname(__DIR__);
$selfTest = in_array('--self-test', $argv, true);
$violations = [];

foreach (SCANNED_DIRS as $dir) {
    $path = $root.'/'.$dir;

    if (! is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), SCANNED_EXTENSIONS, true)) {
            continue;
        }

        $relative = str_replace($root.'/', '', $file->getPathname());

        if (in_array($relative, EXCLUDED_FILES, true)) {
            continue;
        }

        $violations = [...$violations, ...scanSource(
            (string) file_get_contents($file->getPathname()),
            $relative
        )];
    }
}

if ($selfTest) {
    $planted = scanSource('<?php require_once "../frontend/lib/api/client.ts";', '<self-test fixture>');

    if ($planted === []) {
        fwrite(STDERR, "SELF-TEST FAILED: the scan did not flag a planted cross-import.\n");
        exit(1);
    }

    fwrite(STDOUT, "SELF-TEST: the scan flagged the planted cross-import as expected.\n");
    exit(1);
}

if ($violations !== []) {
    fwrite(STDERR, "backend/ must not reference frontend/:\n".implode("\n", $violations)."\n");
    exit(1);
}

fwrite(STDOUT, "No cross-imports from backend/ into frontend/.\n");
exit(0);

/**
 * @return list<string>
 */
function scanSource(string $source, string $label): array
{
    $found = [];

    foreach (FORBIDDEN_PATTERNS as $pattern) {
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
            continue;
        }

        foreach ($matches[0] as [$match, $offset]) {
            $line = substr_count(substr($source, 0, $offset), "\n") + 1;
            $found[] = sprintf('  %s:%d references %s', $label, $line, trim($match));
        }
    }

    return $found;
}
