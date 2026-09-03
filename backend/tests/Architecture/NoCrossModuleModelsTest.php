<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A module never reaches into another module's Eloquent models.
 *
 * A model is a module's private storage shape. Reading another module's model
 * means the two are coupled through a table layout neither of them agreed on,
 * and a migration in one silently breaks the other — at runtime, in whichever
 * code path happens to touch the changed column.
 *
 * Reading another module's TABLE through the query builder is allowed and is
 * how the modules here actually cooperate: it is narrow, greppable, and does
 * not drag an aggregate's behaviour across a boundary.
 *
 * THIS GUARD USED TO MATCH NOTHING. It looked for imports under a `\Models\`
 * namespace, and this codebase has never had one — every model lives under
 * `Domain\`. Its companion "guard the guard" test asserted against a
 * hand-written string (`Tickets\Domain\Models\Ticket`) that does not exist in
 * the repository, so the fiction confirmed itself. Both are fixed below: the
 * pattern is derived from the models that are actually here, and the
 * self-check uses a real class name.
 */
final class NoCrossModuleModelsTest extends TestCase
{
    /**
     * Classes that are Eloquent models, keyed by the module that owns them.
     *
     * Discovered rather than listed: a hard-coded list is a list that stops
     * being true the first time somebody adds a model and does not think to
     * come here.
     *
     * @return array<string, string>  fully-qualified class => owning module
     */
    private function modelsByModule(): array
    {
        $models = [];

        foreach (SourceScanner::phpFiles('app/Modules') as $file) {
            $owner = SourceScanner::moduleOf($file);

            if ($owner === null) {
                continue;
            }

            $source = SourceScanner::codeOnly($file);

            // `extends Model` or `extends Authenticatable` — the two Eloquent
            // bases in use here.
            if (preg_match('/class\s+(\w+)\s+extends\s+(Model|Authenticatable)\b/', $source, $class) !== 1) {
                continue;
            }

            if (preg_match('/namespace\s+([^;]+);/', $source, $namespace) !== 1) {
                continue;
            }

            $models[trim($namespace[1]).'\\'.$class[1]] = $owner;
        }

        return $models;
    }

    public function test_the_scan_finds_the_models_that_exist(): void
    {
        $models = $this->modelsByModule();

        /*
         * The assertion the old version of this file was missing. A guard that
         * has nothing to guard passes forever, and the passing is the problem:
         * it reads as evidence the rule is enforced.
         */
        $this->assertGreaterThan(8, count($models), 'The model scan found almost nothing.');
        $this->assertContains('Customers', $models);
        $this->assertContains('Tickets', $models);
    }

    public function test_no_module_imports_another_modules_models(): void
    {
        $models = $this->modelsByModule();
        $violations = [];

        foreach (SourceScanner::phpFiles('app/Modules') as $file) {
            $owner = SourceScanner::moduleOf($file);

            if ($owner === null) {
                continue;
            }

            $relative = str_replace(SourceScanner::basePath().'/', '', $file);
            $source = SourceScanner::codeOnly($file);

            foreach ($models as $class => $modelOwner) {
                if ($modelOwner === $owner) {
                    continue;
                }

                /*
                 * Imported OR named inline. A fully-qualified call is the same
                 * coupling with the import elided — and is exactly what someone
                 * reaches for when an import-only guard is in the way.
                 */
                $imported = in_array($class, SourceScanner::imports($file), true);
                $inline = str_contains($source, '\\'.$class.'::');

                if ($imported || $inline) {
                    $violations[] = sprintf('%s (module %s) uses %s', $relative, $owner, $class);
                }
            }
        }

        $this->assertSame([], $violations, "Cross-module model use found:\n".implode("\n", $violations));
    }

    public function test_the_detection_fires_on_a_real_class(): void
    {
        /*
         * Against a class that exists, not a hand-written path. The previous
         * version of this test asserted a regex matched
         * `Tickets\Domain\Models\Ticket` — a namespace this repository has
         * never had — so it confirmed nothing about the code it was guarding.
         */
        $models = $this->modelsByModule();

        $this->assertArrayHasKey('App\\Modules\\Customers\\Domain\\Customer', $models);
        $this->assertSame('Customers', $models['App\\Modules\\Customers\\Domain\\Customer']);
    }
}
