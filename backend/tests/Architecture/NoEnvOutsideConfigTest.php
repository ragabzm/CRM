<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * `env()` is legal in config/ and nowhere else.
 *
 * Laravel caches configuration in production. Once `config:cache` has run,
 * every `env()` call outside a config file returns null — so a setting read
 * this way works in development, passes review, and then silently evaluates to
 * null on the deployed machine. The failure is invisible: no exception, just a
 * feature quietly behaving as though it were switched off.
 *
 * Operational values belong in config/. Values an administrator changes belong
 * in the settings registry, which is the point of this story.
 */
final class NoEnvOutsideConfigTest extends TestCase
{
    /** Directories that ship, minus config/ itself. */
    private const SCANNED = ['app', 'routes', 'database', 'bootstrap'];

    public function test_no_shipped_php_file_outside_config_calls_env(): void
    {
        $offenders = [];
        $scanned = 0;

        foreach (self::SCANNED as $directory) {
            // A RELATIVE path: phpFiles() prepends the base itself, and passing
            // an absolute one produced a doubled path that silently matched no
            // files — this guard was passing without reading anything.
            foreach (SourceScanner::phpFiles($directory) as $file) {
                $scanned++;
                // Code only: the docblock on SettingsRegistry explains why env()
                // is unsafe, and must not itself be reported as a use of it.
                $source = SourceScanner::codeOnly($file);

                // `$this->env`, `->env(`, and `getenv` are different things;
                // match a bare call only.
                if (preg_match('/(?<![>$\w])env\s*\(/', $source) === 1) {
                    $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
                }
            }
        }

        $this->assertGreaterThan(50, $scanned, 'The sweep found no source files to check.');

        $this->assertSame(
            [],
            $offenders,
            "env() is only safe inside config/, because config:cache freezes everything else:\n  ".
            implode("\n  ", $offenders),
        );
    }

    public function test_the_guard_would_actually_catch_a_violation(): void
    {
        // A guard that cannot fail is not a guard. This pins the pattern
        // itself, so a future refactor of the regex cannot silently neuter it.
        $this->assertSame(1, preg_match('/(?<![>$\w])env\s*\(/', "\$x = env('APP_KEY');"));
        $this->assertSame(1, preg_match('/(?<![>$\w])env\s*\(/', 'return env("MAIL_HOST", null);'));

        // ...and does not fire on things that merely contain the letters.
        $this->assertSame(0, preg_match('/(?<![>$\w])env\s*\(/', '$this->env('));
        $this->assertSame(0, preg_match('/(?<![>$\w])env\s*\(/', '$config->env('));
        $this->assertSame(0, preg_match('/(?<![>$\w])env\s*\(/', 'setUpEnv('));
    }
}
