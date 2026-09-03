<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The seeders build tickets the way the application does, or not at all.
 *
 * `OnlyTicketsModuleMutatesTicketsTest` scans the module tree and stops at
 * `app/`. Seeders live outside it, which makes them the one place where
 * `DB::table('tickets')->insert()` would pass every existing check — and it is
 * a tempting shortcut, because it is roughly a hundred times faster than
 * eighteen command calls.
 *
 * It is also wrong in ways that do not show up until much later. An inserted
 * ticket has no `ticket.created` event, so its history begins in the middle;
 * it has whatever `version` the insert wrote, so the first edit from the
 * interface is refused as stale; it has no `resolved_at`, so the SLA reads it
 * as still running. The list looks perfect. Everything built on the list is
 * wrong.
 *
 * The same scan covers ambient auth (`auth()->user()` inside a seeder is a
 * null the command would then record as the actor) and the settings table
 * (a seeded row shadows a registry default for every developer, invisibly).
 */
final class DemoSeedersUseCommandsTest extends TestCase
{
    /**
     * Patterns that must not appear, with what each one would break.
     *
     * @var array<string, string>
     */
    private const FORBIDDEN = [
        "DB::table('tickets')" => 'writes tickets directly, skipping the version and the created event',
        "DB::table('ticket_events')" => 'writes history directly into an append-only store',
        "DB::table('ticket_messages')" => 'writes messages directly, skipping their history entries',
        "DB::table('settings')" => 'shadows a registry default (AD-5)',
        'Ticket::create(' => 'writes tickets directly, skipping the version and the created event',
        'TicketEvent::create(' => 'writes history directly into an append-only store',
        'TicketMessage::create(' => 'writes messages directly, skipping their history entries',
        'Auth::user()' => 'reads ambient auth, which is null in a seeder',
        'auth()->user()' => 'reads ambient auth, which is null in a seeder',
        'auth()->id()' => 'reads ambient auth, which is null in a seeder',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function demoSeederFiles(): array
    {
        $files = glob(__DIR__.'/../../database/seeders/Demo*.php') ?: [];

        $cases = [];

        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    #[DataProvider('demoSeederFiles')]
    public function test_it_goes_through_the_commands(string $file): void
    {
        $source = self::codeOf($file);

        foreach (self::FORBIDDEN as $needle => $because) {
            $this->assertStringNotContainsString(
                $needle,
                $source,
                basename($file)." {$because}.",
            );
        }
    }

    /**
     * The file with its comments removed.
     *
     * Scanning the raw text would flag this project's own documentation: the
     * seeders explain at length why `DB::table('tickets')->insert()` is wrong,
     * and a check that punishes writing that sentence down teaches people to
     * stop explaining themselves. What matters is whether the call is CODE.
     */
    private static function codeOf(string $file): string
    {
        $code = '';

        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    public function test_the_scan_actually_found_the_seeders(): void
    {
        /*
         * Guarding the guard. A glob that matches nothing makes every case
         * above pass while checking nothing — which is exactly how
         * NoCrossModuleModelsTest sat green for months against a namespace
         * this repository never had.
         */
        $this->assertGreaterThanOrEqual(7, count(self::demoSeederFiles()));
    }

    public function test_a_forbidden_call_is_still_caught_when_it_is_real_code(): void
    {
        /*
         * The comment-stripping above could quietly excuse everything if it
         * were too eager, so it is exercised against a snippet that IS code.
         */
        $file = tempnam(sys_get_temp_dir(), 'seeder').'.php';
        file_put_contents($file, "<?php\n// DB::table('tickets') in a comment is fine\n\$x = DB::table('tickets')->insert([]);\n");

        $this->assertStringContainsString("DB::table('tickets')", self::codeOf($file));

        unlink($file);
    }

    public function test_a_mention_in_a_comment_is_not_a_violation(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'seeder').'.php';
        file_put_contents($file, "<?php\n/** Never write DB::table('tickets') here. */\n\$x = 1;\n");

        $this->assertStringNotContainsString("DB::table('tickets')", self::codeOf($file));

        unlink($file);
    }

    public function test_the_tickets_seeder_names_the_commands_it_uses(): void
    {
        $source = (string) file_get_contents(__DIR__.'/../../database/seeders/DemoTicketsSeeder.php');

        // The positive half: not writing tickets directly is satisfied by a
        // seeder that creates no tickets at all.
        foreach (['CreateTicket', 'AppendMessage', 'AssignTicket', 'ChangeStatus', 'ResolveTicket'] as $command) {
            $this->assertStringContainsString(
                $command,
                $source,
                "DemoTicketsSeeder does not use {$command}.",
            );
        }
    }
}
