<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Tickets\Domain\Assignment\MappingSource;
use App\Modules\Tickets\Domain\Assignment\MappingTarget;
use PHPUnit\Framework\TestCase;

/**
 * Assignment stays a lookup table.
 *
 * The story names five things that must not appear, and every one of them is
 * something a support team asks for and a developer enjoys building:
 * round-robin, least-open-tickets, direct-to-queue, a per-agent ceiling, and
 * an availability model.
 *
 * Each needs to know who is working right now, and that model was removed with
 * chat presence. Building any of them on top of a table with no availability
 * data means inventing one — a `last_assigned_at` here, an `open_count` there
 * — and those columns are wrong the moment somebody goes on holiday, with
 * nothing to say so.
 *
 * If FR-059 is ever re-derived, deleting this file is the deliberate act that
 * says the availability model came with it.
 */
final class AssignmentStaysALookupTest extends TestCase
{
    /**
     * Names that would each be the first half of a scheduler.
     *
     * Matched against CODE only, so a comment explaining why round-robin is
     * absent is not itself a violation.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'roundRobin',
        'round_robin',
        'leastOpen',
        'least_open',
        'assignment_strategy',
        'AssignmentStrategy',
        'concurrent_ticket_limit',
        'availability_state',
        'AgentAvailability',
    ];

    public function test_no_scheduler_has_appeared(): void
    {
        $found = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                $code = SourceScanner::codeOnly($file);

                foreach (self::FORBIDDEN as $needle) {
                    if (str_contains($code, $needle)) {
                        $found[] = basename($file).' contains '.$needle;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $found,
            "Assignment is a lookup table, not a scheduler.\n".implode("\n", $found),
        );
    }

    public function test_a_mapping_can_be_keyed_on_two_things_and_point_at_two_things(): void
    {
        /*
         * Pinned. A third source or a third target is the change that turns
         * one indexed query into something that needs an evaluator — and the
         * ORDER of the sources is the precedence, so adding one in the middle
         * would silently change which rule wins.
         */
        $this->assertSame(['category', 'department'], MappingSource::values());
        $this->assertSame(['agent', 'department'], MappingTarget::values());
    }

    public function test_the_lookup_makes_one_query_and_evaluates_nothing(): void
    {
        $code = SourceScanner::codeOnly(
            SourceScanner::basePath('app/Modules/Tickets/Domain/Assignment/AutoAssignment.php'),
        );

        // One `->get()`, no loop over rules, no eval, no parser.
        $this->assertSame(1, substr_count($code, '->get()'));

        foreach (['eval(', 'preg_match', 'Expression', 'parse'] as $smell) {
            $this->assertStringNotContainsString($smell, $code);
        }
    }
}
