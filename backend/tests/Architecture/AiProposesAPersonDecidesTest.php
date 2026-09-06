<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * AI proposes, a person decides — held by the build.
 *
 * The rule that outranks every requirement in this epic, and the one that
 * erodes rather than breaks. Nobody ships an auto-sender. What ships is a
 * "send on accept" convenience, then an "apply above 90%" setting because the
 * proposals are usually right, and by the third commit a customer has been
 * emailed something no colleague read.
 *
 * So the refusals are structural: the assist module holds no Tickets command,
 * writes nothing, and there is no confidence anywhere to threshold on.
 */
final class AiProposesAPersonDecidesTest extends TestCase
{
    /** @return list<string> */
    private function sources(): array
    {
        return SourceScanner::phpFiles('app/Modules/Assist');
    }

    public function test_the_assists_hold_no_ticket_command(): void
    {
        $violations = [];

        foreach ($this->sources() as $file) {
            $code = SourceScanner::codeOnly($file);

            /*
             * A command here is a command that could be called without a
             * person — which is the whole definition of an automation.
             * Confirming a proposal goes through the ordinary path with the
             * confirming person's name on it, from the screen.
             */
            foreach ([
                'CreateTicket', 'AppendMessage', 'UpdateTicketAttributes', 'ChangeStatus',
                'ChangeCategory', 'ChangeDepartment', 'AssignTicket', 'ResolveTicket',
                'ReopenTicket', 'EscalateTicket', 'RateTicket',
            ] as $command) {
                if (str_contains($code, $command)) {
                    $violations[] = basename($file).' holds '.$command;
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Nothing in this module may hold a command.\n".implode("\n", $violations),
        );
    }

    public function test_the_assists_write_nothing(): void
    {
        $violations = [];

        foreach ($this->sources() as $file) {
            $code = SourceScanner::codeOnly($file);

            foreach (['->insert(', '->update(', '->delete(', '->save(', '->upsert('] as $write) {
                if (str_contains($code, $write)) {
                    $violations[] = basename($file).' writes via '.$write;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_there_is_no_confidence_to_threshold_on(): void
    {
        $violations = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                $code = SourceScanner::codeOnly($file);

                /*
                 * A score exists to decide when to skip the human. The human
                 * is never skipped, so there is nothing for a score to do —
                 * and a number nobody acts on is a number somebody will
                 * eventually act on.
                 */
                foreach ([
                    'confidence', 'auto_apply', 'autoApply', 'auto_send', 'autoSend',
                    'threshold_percent_ai', 'ai_threshold',
                ] as $needle) {
                    if (stripos($code, $needle) !== false) {
                        $violations[] = basename($file).' contains '.$needle;
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_no_actor_kind_belongs_to_the_machine(): void
    {
        $actors = [];

        foreach (SourceScanner::phpFiles('app/Modules/Tickets/Domain/Actor') as $file) {
            $actors[] = basename($file, '.php');
        }

        sort($actors);

        /*
         * Four kinds and no fifth. A message sent with an AI actor is a
         * message nobody is answerable for — and the test the story asks for
         * by name is satisfied here structurally: there is no such actor to
         * send one with.
         */
        $this->assertSame(
            ['Actor', 'CustomerActor', 'PortalActor', 'StaffActor', 'SystemActor'],
            $actors,
        );
    }
}
