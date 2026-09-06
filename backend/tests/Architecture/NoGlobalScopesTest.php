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

    /**
     * Branch narrows nothing, anywhere, except where the reader asked it to.
     *
     * This test used to assert that branches did not exist at all — the intake
     * was explicit that they were not part of the model. Story 12.1 introduced
     * them deliberately, as a LABEL, and the rule worth guarding changed with
     * them: not "there is no branch" but "branch is not an access boundary".
     *
     * The failure it prevents is not a leak. It is the opposite: a
     * `where('branch_id', $user->branch_id)` added to a query "so people see
     * their own office first", and then a supervisor asking why a ticket they
     * were handed has vanished — with nothing in the logs, because a filter
     * nobody chose leaves none.
     *
     * The ONE legitimate narrowing is the list filter the reader picks, which
     * lives beside every other filter in `TicketListQuery`.
     */
    public function test_branch_narrows_no_query_the_reader_did_not_ask_for(): void
    {
        $allowed = [
            // The filter a user chooses, beside priority and category.
            'TicketListQuery.php',
            // Where the filter is validated and shaped.
            'ListTicketsRequest.php',
            'TicketListFilters.php',
            // The label itself, and the record that inherits it.
            'Branch.php',
            'BranchesController.php',
            'CreateTicket.php',
        ];

        $offenders = [];

        foreach ([...SourceScanner::phpFiles('app'), ...SourceScanner::phpFiles('database')] as $file) {
            if (str_contains($file, '/Database/Migrations/')) {
                // Schema, not a query. A column has to be declared somewhere.
                continue;
            }

            if (in_array(basename($file), $allowed, true)) {
                continue;
            }

            $source = SourceScanner::codeOnly($file);

            if (preg_match('/->\s*(?:where|whereIn|orWhere)\s*\(\s*[\'"]branch_id/', $source) === 1) {
                $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Branch is a label, not an access boundary.\n".implode("\n", $offenders),
        );
    }

    public function test_the_allowed_narrowing_is_a_filter_the_reader_chose(): void
    {
        $query = SourceScanner::codeOnly(
            SourceScanner::basePath('app/Modules/Tickets/Domain/Query/TicketListQuery.php'),
        );

        /*
         * The one place branch narrows anything reads it off the FILTERS the
         * request carried — never off the signed-in user. A `$user->branch_id`
         * here would be the whole rule undone in one line that looks like a
         * convenience.
         */
        $this->assertStringContainsString('$filters->branchIds', $query);
        $this->assertStringNotContainsString('user()->branch_id', $query);
        $this->assertStringNotContainsString('$actor->branch_id', $query);
    }
}
