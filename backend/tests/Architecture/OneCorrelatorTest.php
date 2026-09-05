<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * There is one correlator, and every channel uses it.
 *
 * The rule this defends is not stylistic. Correlation answers "is this message
 * a reply, and to what?" — and a second implementation is how two channels
 * start answering that differently for the same customer. The divergence is
 * invisible until somebody puts two tickets side by side and asks why an email
 * threaded and a form submission did not.
 *
 * It is a tempting thing to write, too: a new adapter has a payload in hand and
 * its own idea of what a thread id looks like, and matching "just this one
 * field" is four lines.
 */
final class OneCorrelatorTest extends TestCase
{
    private const THE_ONE = 'app/Modules/Channels/Domain/Intake/TicketCorrelator.php';

    /** A path a failure message can be read against, not an absolute one. */
    private function relative(string $file): string
    {
        return ltrim(str_replace(SourceScanner::basePath(), '', $file), '/');
    }

    public function test_only_one_class_correlates_messages_to_tickets(): void
    {
        $found = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                if (preg_match('/Correlator\.php$/', $file) === 1) {
                    $found[] = $this->relative($file);
                }
            }
        }

        sort($found);

        $this->assertSame(
            [self::THE_ONE],
            $found,
            "Correlation belongs to one class. Found:\n".implode("\n", $found),
        );
    }

    public function test_no_adapter_reimplements_correlation(): void
    {
        $violations = [];

        /*
         * The two tables correlation reads. An adapter touching either is
         * deciding for itself which ticket a message belongs to, whatever it
         * calls the method.
         */
        $forbidden = ["DB::table('tickets')", "DB::table('ticket_messages')"];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                if (preg_match('/ChannelAdapter\.php$/', $file) !== 1) {
                    continue;
                }

                $code = SourceScanner::codeOnly($file);

                foreach ($forbidden as $needle) {
                    if (str_contains($code, $needle)) {
                        $violations[] = $this->relative($file).' reads '.$needle;
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
