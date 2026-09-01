<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Shared plumbing for the two deptrac-backed tests.
 *
 * The negative case runs the *production* ruleset with only its paths rewritten
 * to the fixture tree. Hand-writing a second ruleset would let the real one rot
 * while the guard-the-guard test kept passing against a copy.
 */
abstract class DeptracTestCase extends TestCase
{
    protected static function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    protected static function runDeptrac(string $configFile): array
    {
        $process = new Process(
            [
                PHP_BINARY, 'vendor/bin/deptrac', 'analyse',
                '--config-file='.$configFile,
                '--no-progress',
                '--no-ansi',
                '--fail-on-uncovered',
                // deptrac caches its AST between runs; a per-config cache file
                // keeps the fixture run from poisoning the real one.
                '--cache-file='.self::basePath('storage/framework/cache/deptrac-'.md5($configFile).'.cache'),
            ],
            self::basePath(),
            null,
            null,
            300,
        );

        $process->run();

        return [
            'exitCode' => (int) $process->getExitCode(),
            'output' => $process->getOutput().$process->getErrorOutput(),
        ];
    }

    /**
     * Writes a copy of $configFile whose analysed paths point at the violation
     * fixtures instead of app/Modules, and returns its path.
     */
    protected static function fixtureConfig(string $configFile): string
    {
        $fixtures = 'tests/Fixtures/ArchViolations/Modules';
        $yaml = (string) file_get_contents(self::basePath($configFile));

        // `paths:` is resolved relative to the config file, and the rewritten
        // config does not live beside the original — so make it absolute first,
        // then repoint the (path-relative) collector regexes.
        $yaml = str_replace('- ./app/Modules', '- '.self::basePath($fixtures), $yaml);
        $yaml = str_replace('app/Modules', $fixtures, $yaml);

        $path = self::basePath('storage/framework/cache/deptrac-fixture-'.basename($configFile));
        file_put_contents($path, $yaml);

        return $path;
    }
}
