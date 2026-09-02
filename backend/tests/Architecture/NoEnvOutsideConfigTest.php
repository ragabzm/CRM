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
 * in the settings registry, which is the point of this story — and the second
 * test here enforces that half: a registry-owned key must never be read through
 * config(), or an administrator's change would be ignored by whichever call
 * site took the config path.
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

    public function test_no_registry_owned_setting_is_read_through_config(): void
    {
        /*
         * Scraped from the SettingDefinition declarations rather than resolved
         * from the container: an architecture test that had to boot the
         * application would fail for reasons that have nothing to do with the
         * rule it is checking. A setting added later is picked up automatically.
         */
        $keys = self::declaredSettingKeys();

        $this->assertGreaterThan(10, count($keys), 'Expected the modules to declare their settings.');

        $offenders = [];
        $scanned = 0;

        foreach (['app', 'routes'] as $directory) {
            foreach (SourceScanner::phpFiles($directory) as $file) {
                $scanned++;
                $code = SourceScanner::codeOnly($file);
                $relative = str_replace(SourceScanner::basePath().'/', '', $file);

                foreach ($keys as $key) {
                    // config('platform.attachments.max_bytes') and friends.
                    $pattern = "/config\\(\\s*['\"]".preg_quote($key, '/')."/";

                    if (preg_match($pattern, $code) === 1) {
                        $offenders[] = "{$relative} reads [{$key}] through config()";
                    }
                }
            }
        }

        $this->assertGreaterThan(50, $scanned, 'The sweep found no source files to check.');

        $this->assertSame(
            [],
            $offenders,
            "An administrator's change would be ignored by these call sites:\n  ".implode("\n  ", $offenders),
        );
    }

    /**
     * Every `key: '...'` passed to a SettingDefinition.
     *
     * @return list<string>
     */
    private static function declaredSettingKeys(): array
    {
        $keys = [];

        foreach (SourceScanner::phpFiles('app/Modules') as $file) {
            $code = SourceScanner::codeOnly($file);

            if (! str_contains($code, 'SettingDefinition')) {
                continue;
            }

            if (preg_match_all("/key:\s*'([a-z0-9_.]+)'/i", $code, $matches) > 0) {
                foreach ($matches[1] as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    public function test_the_key_list_is_actually_discovered(): void
    {
        $keys = self::declaredSettingKeys();

        // Guards the scraper: an empty list would make the sweep above pass
        // while checking nothing at all.
        $this->assertContains('tickets.auto_close_hours', $keys);
        $this->assertContains('platform.attachments.max_bytes', $keys);
        $this->assertContains('sla.working_hours', $keys);
    }

    public function test_that_guard_would_catch_a_violation(): void
    {
        // A guard that cannot fail is not a guard.
        $key = 'platform.attachments.max_bytes';
        $bad = "\$max = config('platform.attachments.max_bytes', 1024);";

        $this->assertSame(1, preg_match("/config\\(\\s*['\"]".preg_quote($key, '/')."/", $bad));

        // ...and the documented config FALLBACK, which is a different key, is
        // left alone.
        $good = "\$max = config('attachments.defaults.max_bytes', 1024);";
        $this->assertSame(0, preg_match("/config\\(\\s*['\"]".preg_quote($key, '/')."/", $good));
    }
}
