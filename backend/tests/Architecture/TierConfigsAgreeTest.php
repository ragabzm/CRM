<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every module has a tier, in all three places that record one.
 *
 * `app/Modules/module-tiers.php` is the source of truth, `deptrac.yaml` polices
 * module-to-module edges, and `deptrac-tiers.yaml` polices direction. A module
 * missing from either config is not refused — it is UNCOVERED, which only
 * fails the build once something happens to depend on it across a boundary.
 *
 * That is not hypothetical. Ai, Assist and Reporting were each added by a story
 * and each missed `deptrac-tiers.yaml`. Nothing noticed for three stories,
 * until the chatbot became the first thing to reach Ai from another tier — and
 * the failure then read as a tier violation in the story that was innocent.
 */
final class TierConfigsAgreeTest extends TestCase
{
    /** @return list<string> */
    private function modules(): array
    {
        /** @var array<string, int> $tiers */
        $tiers = require SourceScanner::basePath('app/Modules/module-tiers.php');

        return array_keys($tiers);
    }

    public function test_every_module_directory_has_a_declared_tier(): void
    {
        $declared = $this->modules();
        $onDisk = [];

        foreach (scandir(SourceScanner::basePath('app/Modules')) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir(SourceScanner::basePath('app/Modules/'.$entry))) {
                $onDisk[] = $entry;
            }
        }

        sort($declared);
        sort($onDisk);

        $this->assertSame(
            $declared,
            $onDisk,
            'A module exists with no tier, or a tier is declared for a module that is gone.',
        );
    }

    public function test_every_module_is_covered_by_both_deptrac_configs(): void
    {
        foreach (['deptrac.yaml', 'deptrac-tiers.yaml'] as $config) {
            $source = (string) file_get_contents(SourceScanner::basePath($config));

            foreach ($this->modules() as $module) {
                /*
                 * Matched against the collector paths, which is where a module
                 * is claimed by a layer. Anywhere else in the file — a comment,
                 * a ruleset entry — is not coverage.
                 */
                $this->assertMatchesRegularExpression(
                    '#value: app/Modules/(?:\('.'[^)]*\b'.preg_quote($module, '#').'\b[^)]*\)|'.preg_quote($module, '#').')/\.\*#',
                    $source,
                    "[{$module}] has no layer in {$config}. It is uncovered, which fails the build only ".
                    'once something reaches it across a boundary — in a story that had nothing to do with it.',
                );
            }
        }
    }
}
