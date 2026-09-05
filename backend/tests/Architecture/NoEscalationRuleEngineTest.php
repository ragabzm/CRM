<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Tickets\Domain\Enum\TicketStatus;
use PHPUnit\Framework\TestCase;

/**
 * Escalation stays one property, one action and one condition.
 *
 * The story is explicit and unusually specific about what must NOT appear, and
 * every item on that list is something a support team asks for on day two and
 * a developer is happy to build: a rules table, an ordering, a per-rule
 * enable/disable, an execution log, escalation levels.
 *
 * Each of those is reasonable on its own. Together they are a workflow engine,
 * and this product does not have one — so the second rule would arrive with no
 * conflict resolution, no dry run and no way to see why a ticket ended up
 * where it did. The scope reduction is the design, and a comment saying so is
 * not enough to hold it.
 *
 * If FR-055–FR-057 are ever re-derived and a rule engine is genuinely wanted,
 * deleting this file is the deliberate act that says so.
 */
final class NoEscalationRuleEngineTest extends TestCase
{
    /**
     * Names that would each be the first half of a rule engine.
     *
     * Matched against class and table names, not against prose: a comment
     * explaining why there is no rule table must not be a violation of the
     * rule it explains.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'escalation_rules',
        'EscalationRule',
        'EscalationLevel',
        'escalation_level',
        'RuleEngine',
        'ConditionBuilder',
        'RuleExecutionLog',
    ];

    public function test_no_rule_engine_has_appeared(): void
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
            "Escalation is one condition and two fixed actions.\n".implode("\n", $found),
        );
    }

    public function test_the_lifecycle_still_has_exactly_four_states(): void
    {
        /*
         * `Escalated` is NOT one of them, and adding it would be the quietest
         * way to turn escalation into a status — at which point every filter,
         * every count and every transition rule has a fifth value to learn,
         * and a ticket can no longer be both escalated and open.
         */
        $this->assertSame(
            ['open', 'pending', 'resolved', 'closed'],
            TicketStatus::values(),
        );
    }

    public function test_the_only_automatic_escalation_is_the_breach_one(): void
    {
        $callers = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                $code = SourceScanner::codeOnly($file);

                if (! str_contains($code, 'EscalateTicket') || str_contains($file, 'EscalateTicket.php')) {
                    continue;
                }

                $callers[] = basename($file);
            }
        }

        sort($callers);

        /*
         * Pinned. AD-18 allows exactly four bounded automations — auto-close,
         * breach recording, breach escalation, assignment on creation — and
         * this story adds no fifth. A new caller here is a new automation, and
         * it has to be a decision rather than an import.
         */
        $this->assertSame(
            ['EscalateOnBreach.php', 'TicketEscalationController.php'],
            $callers,
        );
    }
}
