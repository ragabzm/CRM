<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The one machine that reaches a customer stays the smallest one.
 *
 * The chatbot is the single exception this epic allows: it answers without a
 * colleague having read what it wrote. Everything else in Epic 9 is a proposal
 * to a person, and the exception is only safe while it stays exactly this
 * narrow — answer from published articles, or fetch somebody.
 *
 * The commit that would end that is not a bad one. It is "the bot can obviously
 * close a ticket it just resolved", and then "it can set the category while it
 * is there", and by the third one a customer's request has been closed by
 * something nobody can be asked about.
 */
final class TheChatbotHoldsNoCommandTest extends TestCase
{
    private function chatbot(): string
    {
        return SourceScanner::codeOnly(
            SourceScanner::basePath('app/Modules/Channels/Domain/Chat/Chatbot.php'),
        );
    }

    public function test_it_holds_no_command_but_the_one_that_appends_a_message(): void
    {
        $code = $this->chatbot();

        /*
         * `AppendMessage` is the exception and the only one: the chatbot says
         * things, and it says them through the same command a person uses. It
         * does not write ticket rows and does not invent a second way a chat
         * becomes a ticket.
         */
        foreach ([
            'CreateTicket', 'UpdateTicketAttributes', 'ChangeStatus', 'ChangeCategory',
            'ChangeDepartment', 'AssignTicket', 'ResolveTicket', 'ReopenTicket',
            'EscalateTicket', 'RateTicket', 'CreateTask', 'CreateReminder',
        ] as $command) {
            $this->assertStringNotContainsString(
                $command,
                $code,
                "The chatbot holds [{$command}]. It answers or it fetches a person; it does nothing else.",
            );
        }

        $this->assertStringContainsString('AppendMessage', $code);
    }

    public function test_it_sends_no_email(): void
    {
        $code = $this->chatbot();

        foreach (['Mail::', 'MailMessage', 'Notification', 'notify('] as $needle) {
            $this->assertStringNotContainsString($needle, $code);
        }
    }

    public function test_it_never_writes_a_ticket_row_itself(): void
    {
        $code = $this->chatbot();

        /*
         * It reads the tickets table for the conversation history and writes
         * only through the command. A direct write here would bypass the
         * version guard, the history entry and the reopen rule at once.
         */
        $this->assertDoesNotMatchRegularExpression(
            "/DB::table\\(\\s*'tickets'\\s*\\)->[a-zA-Z]*\\s*(?:update|insert|delete)/",
            $code,
        );
    }

    public function test_it_reaches_a_provider_only_through_the_port(): void
    {
        $code = $this->chatbot();

        /*
         * No provider client, no model name, no API key — the widget's path to
         * a model runs through the Story 9.1 port and its sanitiser, and there
         * is no second way out of the building.
         */
        foreach (['OpenAI\\', 'Anthropic\\', 'GuzzleHttp\\Client', 'api.openai.com', 'Http::'] as $needle) {
            $this->assertStringNotContainsString($needle, $code);
        }

        $this->assertStringContainsString('AiProvider', $code);
    }

    public function test_it_asks_only_for_public_published_articles(): void
    {
        $code = $this->chatbot();

        /*
         * `customerVisibleOnly: true`, at the query. Internal, draft and
         * archived articles are excluded before the rows are loaded — a filter
         * applied afterwards is a filter one refactor away from not being
         * applied, and the thing it would leak is an internal note to a
         * customer.
         */
        preg_match_all('/->search\(([^;]*?)\)/s', $code, $calls);

        $this->assertNotEmpty($calls[1], 'The chatbot no longer searches the knowledge base.');

        foreach ($calls[1] as $arguments) {
            $parts = array_map(trim(...), explode(',', $arguments));

            // `search(term, locale, customerVisibleOnly, limit)` — the third
            // argument is the whole rule, so it is read positionally rather
            // than by looking for the word `true` somewhere in the file.
            $this->assertSame(
                'true',
                $parts[2] ?? '',
                'The chatbot asked for articles that are not public and published.',
            );
        }
    }

    public function test_no_turn_is_streamed(): void
    {
        $code = $this->chatbot();

        // A held-open connection is exactly what AD-21 refuses, and the
        // widget's polling interval already governs this surface.
        foreach (['stream', 'Stream', 'EventSource', 'text/event-stream'] as $needle) {
            $this->assertStringNotContainsString($needle, $code);
        }
    }
}
