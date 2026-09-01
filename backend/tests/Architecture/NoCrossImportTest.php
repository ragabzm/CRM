<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The backend half of the two-way independence proof. The frontend half lives
 * in frontend/__tests__/no-cross-import.test.ts.
 */
final class NoCrossImportTest extends TestCase
{
    public function test_the_backend_does_not_reference_the_frontend(): void
    {
        $process = new Process(
            [PHP_BINARY, 'scripts/check-no-cross-import.php'],
            SourceScanner::basePath(),
            null,
            null,
            120,
        );

        $process->run();

        $this->assertSame(
            0,
            $process->getExitCode(),
            "backend/ must not reference frontend/:\n".$process->getOutput().$process->getErrorOutput()
        );
    }

    public function test_the_scan_fails_on_a_planted_reference(): void
    {
        $process = new Process(
            [PHP_BINARY, 'scripts/check-no-cross-import.php', '--self-test'],
            SourceScanner::basePath(),
            null,
            null,
            120,
        );

        $process->run();

        $this->assertNotSame(
            0,
            $process->getExitCode(),
            'The cross-import scan did not fail on its own planted fixture, so it is not guarding anything.'
        );
    }
}
