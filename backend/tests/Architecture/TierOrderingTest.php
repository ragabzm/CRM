<?php

declare(strict_types=1);

namespace Tests\Architecture;

/**
 * The same ordering checked at tier granularity, independently of how modules
 * are grouped into tiers. Same-tier edges are deliberately out of scope here —
 * ModuleBoundariesTest owns that rule.
 */
final class TierOrderingTest extends DeptracTestCase
{
    private const CONFIG = 'deptrac-tiers.yaml';

    public function test_no_module_depends_on_an_equal_or_higher_tier(): void
    {
        $result = self::runDeptrac(self::CONFIG);

        $this->assertSame(
            0,
            $result['exitCode'],
            "Tier ordering violations found.\n\n".$result['output']
        );
    }

    public function test_the_ruleset_rejects_a_module_reaching_up_a_tier(): void
    {
        $result = self::runDeptrac(self::fixtureConfig(self::CONFIG));

        $this->assertNotSame(
            0,
            $result['exitCode'],
            "The tier ruleset accepted a known violation.\n\n".$result['output']
        );
        $this->assertStringContainsString('ReachesUpATier', $result['output']);
    }
}
