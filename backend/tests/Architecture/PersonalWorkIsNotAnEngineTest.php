<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\TestCase;

/**
 * Tasks, reminders and mentions stay the least mechanism that works.
 *
 * All three are the kind of feature that is usually built as an engine, and
 * each grows the same way: a task gets a description, then an assignee, then
 * sub-tasks; a reminder gets a snooze, then a repeat rule; a mention gets a
 * followers list, then a subscription, then a per-type preference matrix.
 *
 * None of those arrives as a bad decision. Each is one reasonable-looking
 * commit, and by the fifth there is a workflow product inside a helpdesk with
 * its own permissions and its own screens. This file is where the refusal is
 * written down, so re-deciding it has to be deliberate — deleting this file is
 * the act that says so.
 */
final class PersonalWorkIsNotAnEngineTest extends TestCase
{
    /**
     * Names that would each be the first half of something bigger.
     *
     * Matched against CODE only, so a comment explaining why there is no
     * snooze is not itself a violation of the rule it explains.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'snooze',
        'recurrence',
        'recurring',
        'repeat_every',
        'sub_task',
        'subtask',
        'parent_task_id',
        'task_assignee',
        'followers',
        'ticket_follow',
        'subscription',
        'digest',
        'quiet_hours',
        'notification_preference',
    ];

    public function test_no_engine_has_grown_out_of_it(): void
    {
        $found = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                $code = SourceScanner::codeOnly($file);

                foreach (self::FORBIDDEN as $needle) {
                    if (stripos($code, $needle) !== false) {
                        $found[] = basename($file).' contains '.$needle;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $found,
            "Personal work is the least mechanism that works.\n".implode("\n", $found),
        );
    }

    public function test_tasks_are_a_tab_and_never_a_destination(): void
    {
        /*
         * Read as SOURCE rather than through the router: these architecture
         * tests do not boot the application, and booting one just to enumerate
         * routes would make the cheapest test in the suite the slowest.
         */
        $routes = (string) file_get_contents(__DIR__.'/../../routes/api.php');

        $meBlock = $this->personalBlock($routes);

        $this->assertNotSame('', $meBlock, "The `/me` route group has gone.");

        // Everything personal, with the `/me` group cut out. What is left must
        // mention none of it.
        $elsewhere = str_replace($meBlock, '', $routes);

        foreach (["'/tasks'", "'/reminders'", "'/mentions'"] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $elsewhere,
                "A top-level {$needle} collection has appeared. Tasks are a tab on Home, ".
                'not a destination — and a route of their own is the API half of a sidebar entry.',
            );
        }
    }

    /** The body of the `Route::prefix('me')` group, or an empty string. */
    private function personalBlock(string $routes): string
    {
        $start = strpos($routes, "Route::prefix('me')");

        if ($start === false) {
            return '';
        }

        $end = strpos($routes, '});', $start);

        return $end === false ? '' : substr($routes, $start, $end - $start + 3);
    }

    public function test_a_task_cannot_be_handed_to_anybody(): void
    {
        $create = new \ReflectionMethod(
            \App\Modules\Tickets\Domain\Personal\Commands\CreateTask::class,
            'handle',
        );

        $names = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $create->getParameters(),
        );

        /*
         * The owner is the CREATOR and is not something a caller chooses. A
         * task that could be created for somebody else is an assignment, and
         * assignment already exists on the ticket — two ways to give a
         * colleague work is one too many, and the quiet one is the one nobody
         * watches.
         */
        $this->assertContains('ownerId', $names);
        $this->assertNotContains('assigneeId', $names);
        $this->assertNotContains('userId', $names);
    }

    public function test_the_notification_channels_are_the_ones_story_5_4_set(): void
    {
        foreach ([
            \App\Modules\Tickets\Notifications\ReminderDue::class,
            \App\Modules\Tickets\Notifications\MentionedInNote::class,
        ] as $class) {
            $notification = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

            /*
             * `database` for the bell, `mail` for somebody who is not looking
             * at the application. No third channel, and no per-type matrix
             * deciding which of them this one uses — that question is one
             * nobody needs asked at five triggers.
             */
            $this->assertSame(['database', 'mail'], $notification->via(new \stdClass));
        }
    }
}
