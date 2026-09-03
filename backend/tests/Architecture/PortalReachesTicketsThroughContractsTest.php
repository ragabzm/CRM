<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The portal knows one thing about tickets: the contract.
 *
 * Portal is T4 and Tickets is T3, so depending downward is legal and deptrac
 * will not object. That is not enough here. The portal is the surface a
 * CUSTOMER sees, and every extra thing it can reach is another thing that could
 * end up on their screen — an internal note, an assignee's name, a priority, an
 * SLA countdown.
 *
 * Confining it to `Tickets\Contracts` means the boundary is a small, readable
 * interface rather than a habit of not calling the wrong method.
 */
final class PortalReachesTicketsThroughContractsTest extends TestCase
{
    public function test_the_portal_imports_only_ticket_contracts(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app/Modules/Portal') as $file) {
            $relative = str_replace(SourceScanner::basePath().'/', '', $file);

            foreach (SourceScanner::imports($file) as $import) {
                if (! str_starts_with($import, 'App\\Modules\\Tickets\\')) {
                    continue;
                }

                if (str_starts_with($import, 'App\\Modules\\Tickets\\Contracts\\')) {
                    continue;
                }

                $offenders[] = "{$relative} imports {$import}";
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_the_portal_does_not_name_a_ticket_class_inline_either(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app/Modules/Portal') as $file) {
            $code = SourceScanner::codeOnly($file);

            /*
             * A fully-qualified call is the same coupling with the import
             * elided — and is exactly what somebody reaches for when an
             * import-only rule is in the way.
             */
            if (preg_match('/\\\\App\\\\Modules\\\\Tickets\\\\(?!Contracts)/', $code) === 1) {
                $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_the_portal_actually_uses_the_contract(): void
    {
        // The rules above pass trivially if the portal talks to nothing.
        $found = false;

        foreach (SourceScanner::phpFiles('app/Modules/Portal') as $file) {
            if (in_array('App\\Modules\\Tickets\\Contracts\\CustomerRequestGateway', SourceScanner::imports($file), true)) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'The portal reads requests through no contract at all.');
    }

    public function test_the_scan_covered_the_portal(): void
    {
        $this->assertGreaterThan(5, count(SourceScanner::phpFiles('app/Modules/Portal')));
    }
}
