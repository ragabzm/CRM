<?php

declare(strict_types=1);

namespace Tests\Architecture;

/**
 * Enforces the module graph: a module may depend only on modules in a strictly
 * lower tier, and the three T4 modules must not reach sideways into each other.
 */
final class ModuleBoundariesTest extends DeptracTestCase
{
    private const CONFIG = 'deptrac.yaml';

    public function test_the_module_graph_has_no_violations(): void
    {
        $result = self::runDeptrac(self::CONFIG);

        $this->assertSame(
            0,
            $result['exitCode'],
            "Module boundary violations found.\n\n".$result['output']
        );
    }

    public function test_the_ruleset_rejects_a_module_reaching_up_a_tier(): void
    {
        $result = self::runDeptrac(self::fixtureConfig(self::CONFIG));

        $this->assertNotSame(
            0,
            $result['exitCode'],
            "The ruleset accepted a known violation, so it is not actually guarding anything.\n\n".$result['output']
        );
        $this->assertStringContainsString('ReachesUpATier', $result['output']);
    }

    public function test_the_ruleset_rejects_one_t4_module_calling_another(): void
    {
        $result = self::runDeptrac(self::fixtureConfig(self::CONFIG));

        $this->assertStringContainsString(
            'CallsSameTier',
            $result['output'],
            'T4 modules must not be allowed to depend on each other.'
        );
    }
}
