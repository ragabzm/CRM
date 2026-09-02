<?php

declare(strict_types=1);

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Small helper for the tests that reason about source text rather than about
 * the dependency graph deptrac builds.
 */
final class SourceScanner
{
    public static function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
    }

    /**
     * @return list<string> Absolute paths to every .php file under $relativeDir.
     */
    public static function phpFiles(string $relativeDir): array
    {
        $root = self::basePath($relativeDir);

        if (! is_dir($root)) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Every fully-qualified name brought in by a `use` statement.
     *
     * Deliberately ignores names written inline (\App\Modules\X\Y::class in the
     * middle of an expression); the tests that use this also assert on the raw
     * source where that matters.
     *
     * @return list<string>
     */
    public static function imports(string $file): array
    {
        $source = (string) file_get_contents($file);

        preg_match_all('/^\s*use\s+(?:function\s+|const\s+)?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/m', $source, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** The module a file under app/Modules belongs to, or null. */
    public static function moduleOf(string $file): ?string
    {
        return preg_match('#/app/Modules/([A-Za-z0-9_]+)/#', $file, $m) === 1 ? $m[1] : null;
    }

    /** @return list<string> */
    public static function moduleNames(): array
    {
        /** @var array<string, int> $tiers */
        $tiers = require self::basePath('app/Modules/module-tiers.php');

        return array_keys($tiers);
    }

    /**
     * A file's source with comments and docblocks removed.
     *
     * Guards that grep for a forbidden word must read CODE. Scanning raw text
     * makes a comment explaining why the thing is forbidden into a violation of
     * that same rule, which is both absurd and a real incentive to write worse
     * comments.
     */
    public static function codeOnly(string $file): string
    {
        $source = (string) file_get_contents($file);
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        return $code;
    }
}
