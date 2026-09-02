<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Domain commands take their Actor as a parameter and read nothing ambient.
 *
 * A command that called `auth()` would work from a controller and fail from
 * everywhere else — the auto-close job, a console command, a queue worker, a
 * test. Those callers are the reason the Actor is explicit, and the failure
 * would not appear until one of them ran in production at 3am.
 */
final class CommandsDoNotReadAmbientAuthTest extends TestCase
{
    /** @var list<string> */
    private const FORBIDDEN = [
        '/(?<![\w>$])auth\s*\(/',
        '/(?<![\w>$])request\s*\(/',
        '/(?<![\w>$])session\s*\(/',
        '/\bAuth::/',
        '/\bRequest::/',
        '/\bSession::/',
    ];

    /** Directories where the domain lives and ambient state is banned. */
    private const DOMAIN_PATHS = [
        'app/Modules/Tickets/Domain',
    ];

    public function test_no_domain_file_reads_ambient_state(): void
    {
        $violations = [];
        $scanned = 0;

        foreach (self::DOMAIN_PATHS as $path) {
            foreach (SourceScanner::phpFiles($path) as $file) {
                $scanned++;
                $code = SourceScanner::codeOnly($file);
                $relative = str_replace(SourceScanner::basePath().'/', '', $file);

                foreach (self::FORBIDDEN as $pattern) {
                    if (preg_match($pattern, $code) === 1) {
                        $violations[] = "{$relative} matches {$pattern}";
                    }
                }
            }
        }

        $this->assertGreaterThan(5, $scanned, 'The sweep found no domain files to check.');

        $this->assertSame(
            [],
            $violations,
            "Domain code takes its Actor as a parameter:\n  ".implode("\n  ", $violations),
        );
    }

    public function test_every_command_takes_an_actor_first(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app/Modules/Tickets/Domain/Commands') as $file) {
            $code = SourceScanner::codeOnly($file);

            // Only the classes with a handle(); the inputs and the trait have
            // no such method.
            if (preg_match('/public function handle\s*\(([^)]*)\)/s', $code, $m) !== 1) {
                continue;
            }

            if (! str_contains($m[1], 'Actor $actor')) {
                $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
            }
        }

        // First parameter, every time, so "who did this?" is never an
        // afterthought a caller can leave out.
        $this->assertSame([], $offenders, 'Every command handle() takes Actor $actor: '.implode(', ', $offenders));
    }

    public function test_the_guard_would_catch_a_violation(): void
    {
        $this->assertSame(1, preg_match('/(?<![\w>$])auth\s*\(/', '$user = auth()->user();'));
        $this->assertSame(1, preg_match('/\bAuth::/', 'Auth::user()'));
        // ...and does not fire on an unrelated method that merely ends in the
        // same letters.
        $this->assertSame(0, preg_match('/(?<![\w>$])auth\s*\(/', '$this->reauth()'));
    }
}
