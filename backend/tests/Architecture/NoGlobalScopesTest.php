<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * No global Eloquent scopes anywhere in the module tree.
 *
 * A global scope applies invisibly, which sounds safer than an explicit call
 * and is worse. It silently filters admin tooling, exports, reports and queued
 * jobs that legitimately need every row; and the escape hatch,
 * `withoutGlobalScope`, is a blunt instrument that removes the rule entirely
 * rather than adjusting it.
 *
 * The row-level rule lives in TicketVisibility, called explicitly at each query
 * site, where it is greppable and reviewable.
 */
final class NoGlobalScopesTest extends TestCase
{
    public function test_no_module_registers_a_global_scope(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match('/\baddGlobalScope\s*\(/', $source) === 1) {
                $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, "Global scopes found:\n".implode("\n", $offenders));
    }

    public function test_no_module_declares_a_scoped_by_attribute(): void
    {
        // Laravel 12+ can attach a global scope declaratively; same objection.
        $offenders = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match('/#\[\s*ScopedBy\b/', $source) === 1) {
                $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_the_row_rule_lives_in_exactly_one_place(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            if (str_ends_with($file, 'TicketVisibility.php')) {
                continue;
            }

            $source = (string) file_get_contents($file);

            // A second copy of "assignee_id or null" is a second thing to get
            // wrong, and the wrong one is the one nobody tested.
            if (preg_match('/orWhereNull\s*\(\s*[\'"]assignee_id/', $source) === 1) {
                $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_there_is_no_branches_table_or_column(): void
    {
        $offenders = [];

        foreach ([...SourceScanner::phpFiles('app'), ...SourceScanner::phpFiles('database')] as $file) {
            $source = (string) file_get_contents($file);

            // The intake is explicit that branches are not part of this model.
            if (preg_match('/\bbranch_id\b|[\'"]branches[\'"]/', $source) === 1) {
                $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }
}
