<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * One write path, enforced.
 *
 * The version guard, the lifecycle table and the event append all live in the
 * Tickets commands. A write from anywhere else skips all three — and the
 * damage is invisible: a ticket whose status changed with no event, or whose
 * version never moved, looks perfectly normal until someone tries to work out
 * what happened to it.
 */
final class OnlyTicketsModuleMutatesTicketsTest extends TestCase
{
    /** Tables only the Tickets module may write to. */
    private const OWNED_TABLES = ['tickets', 'ticket_events'];

    /** Writes through the query builder. */
    private const WRITE_METHODS = ['insert', 'update', 'delete', 'upsert', 'truncate', 'insertGetId'];

    public function test_no_other_module_writes_to_a_tickets_table(): void
    {
        $violations = [];
        $scanned = 0;

        foreach (SourceScanner::phpFiles('app/Modules') as $file) {
            $module = SourceScanner::moduleOf($file);

            if ($module === null || $module === 'Tickets') {
                continue;
            }

            $scanned++;
            $code = SourceScanner::codeOnly($file);
            $relative = str_replace(SourceScanner::basePath().'/', '', $file);

            foreach (self::OWNED_TABLES as $table) {
                foreach (self::WRITE_METHODS as $method) {
                    // DB::table('tickets')->update(...) and friends, allowing
                    // for a chained builder in between.
                    $pattern = sprintf("/table\\(\\s*['\"]%s['\"].{0,200}?->%s\\s*\\(/s", $table, $method);

                    if (preg_match($pattern, $code) === 1) {
                        $violations[] = "{$relative} writes to [{$table}] via ->{$method}()";
                    }
                }
            }

            // The models are Tickets' private storage representation.
            if (preg_match('/App\\\\Modules\\\\Tickets\\\\Domain\\\\(Ticket|TicketEvent)\b/', $code) === 1) {
                $violations[] = "{$relative} references a Tickets domain model directly";
            }
        }

        // Proves the sweep read files: a path typo would otherwise make this
        // pass by scanning nothing.
        $this->assertGreaterThan(50, $scanned, 'The sweep found no source files to check.');

        $this->assertSame(
            [],
            $violations,
            "Only App\\Modules\\Tickets may write a ticket:\n  ".implode("\n  ", $violations),
        );
    }

    public function test_the_guard_would_catch_a_violation(): void
    {
        // A guard that cannot fail is not a guard.
        $bad = "DB::table('tickets')->where('id', \$id)->update(['status' => 'closed']);";

        $this->assertSame(
            1,
            preg_match("/table\\(\\s*['\"]tickets['\"].{0,200}?->update\\s*\\(/s", $bad),
        );
    }
}
