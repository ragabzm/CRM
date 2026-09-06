<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Live chat stays polling, and stays a list.
 *
 * Two refusals, and both are the kind that get overturned by one reasonable
 * commit.
 *
 * THE FIRST is that there is no persistent connection. A WebSocket is the
 * obvious answer to "chat feels laggy", and it arrives with a broker, a second
 * runtime to deploy and keep alive, its own scaling story, and a whole class
 * of failure — a socket that is open but dead — that polling simply does not
 * have. The accepted trade is that a message can take one interval to appear,
 * and the widget says so rather than hiding it.
 *
 * THE SECOND is that the waiting list is a LIST. Routing, priority and
 * assignment strategy all need to know who is available, and availability is
 * the state this story rules out by name — so the first routing rule would
 * drag presence in behind it.
 *
 * If either is genuinely wanted later, deleting this file is the deliberate
 * act that says so.
 */
final class ChatHasNoSecondRuntimeTest extends TestCase
{
    /**
     * Names that would each be the first half of something much larger.
     *
     * Matched against CODE only, so a comment explaining why there is no
     * WebSocket is not itself a violation of the rule it explains.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'websocket',
        'WebSocket',
        'EventSource',
        'text/event-stream',
        'long_poll',
        'longPoll',
        'pusher',
        'Pusher',
        'centrifugo',
        'socket.io',
        'agent_availability',
        'presence',
        'typing_indicator',
        'read_receipt',
        'routing_rule',
        'queue_position',
    ];

    public function test_no_second_runtime_has_appeared(): void
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
            "Chat polls, and the waiting list is a list.\n".implode("\n", $found),
        );
    }

    public function test_the_conversation_carries_no_availability_or_routing_column(): void
    {
        $migration = (string) file_get_contents(SourceScanner::basePath(
            'app/Modules/Channels/Database/Migrations/2026_09_29_000200_create_chat_conversations_table.php',
        ));

        /*
         * Read as SOURCE rather than from the schema, so this fails when
         * somebody WRITES the column — not months later when a deployment
         * runs the migration.
         */
        foreach ([
            "'position'",
            "'priority'",
            "'routing",
            "'skill",
            "'available",
            "'presence",
            "'typing",
            "'read_receipt",
            // The transcript is ticket messages. A blob here would be a second
            // kind of ticket history no screen, search or export knows about.
            "'transcript'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $migration,
                "A {$forbidden} column has appeared on chat_conversations.",
            );
        }
    }

    public function test_the_widget_reaches_only_the_chat_surface(): void
    {
        $routes = (string) file_get_contents(SourceScanner::basePath('routes/api.php'));

        $start = strpos($routes, "Route::prefix('chat')->name('chat.')");
        $this->assertNotFalse($start, 'The public chat route group has gone.');

        $end = strpos($routes, '});', $start);
        $group = substr($routes, $start, (int) $end - $start);

        /*
         * Four endpoints and no fifth. A visitor's token is the least
         * trustworthy credential this product issues — it lives in an iframe
         * on somebody else's website — so what it can reach is pinned rather
         * than reviewed.
         */
        preg_match_all('/Route::(get|post|put|patch|delete)\(/', $group, $matches);

        $this->assertCount(
            // Read the allow-list, open, rejoin, read, send, close. Six, and
            // no seventh.
            6,
            $matches[0],
            'The chat widget group has grown. Everything here is reachable with no session at all.',
        );

        foreach (['TicketsController', 'CustomerContextController', 'TicketMessagesController'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $group);
        }
    }
}
