<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A module's Eloquent models are its private storage representation. Crossing
 * that line couples two modules to one table layout, which is exactly the drift
 * the tiering is meant to prevent — modules talk through Contracts/ instead.
 */
final class NoCrossModuleModelsTest extends TestCase
{
    public function test_no_module_imports_another_modules_models(): void
    {
        $violations = [];

        foreach (SourceScanner::phpFiles('app/Modules') as $file) {
            $owner = SourceScanner::moduleOf($file);

            if ($owner === null) {
                continue;
            }

            foreach (SourceScanner::imports($file) as $import) {
                if (preg_match('#^App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\.*Models\\\\#', $import, $m) !== 1) {
                    continue;
                }

                if ($m[1] !== $owner) {
                    $violations[] = sprintf(
                        '%s (module %s) imports %s',
                        str_replace(SourceScanner::basePath().'/', '', $file),
                        $owner,
                        $import
                    );
                }
            }
        }

        $this->assertSame([], $violations, "Cross-module model imports found:\n".implode("\n", $violations));
    }

    /**
     * Guards the guard: the detection above must actually fire on a known-bad
     * import rather than silently matching nothing.
     */
    public function test_the_detection_matches_a_known_bad_import(): void
    {
        $bad = 'App\\Modules\\Tickets\\Domain\\Models\\Ticket';

        $this->assertSame(
            1,
            preg_match('#^App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\.*Models\\\\#', $bad, $m)
        );
        $this->assertSame('Tickets', $m[1]);
    }
}
