<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * The one place that decides whether a demo seeder may write.
 *
 * `DevAccountsSeeder` carries this check inline, and copying that `if` into
 * seven more files would mean seven chances to write `!== 'production'`
 * instead — which allows staging, where real people's data lives. The guard is
 * an allow-list of the two environments that are definitionally disposable,
 * and it is written once.
 *
 * Not a trait: a trait would still be seven copies of the call, and this way
 * the guard is a thing that can be tested on its own.
 */
final class DemoEnvironment
{
    /**
     * @param  class-string  $seeder
     */
    public static function allows(?Command $command, string $seeder): bool
    {
        if (App::environment(['local', 'testing'])) {
            return true;
        }

        $command?->warn(class_basename($seeder).' skipped: local development only.');

        return false;
    }

    /**
     * Run a dependency, unless its data is already there.
     *
     * NOT `callOnce`. That remembers per PROCESS — `Seeder::$called` is a
     * static — which is a different question from whether the rows exist. A
     * test that resets the database between cases, or anything that seeds
     * twice in one process around a wipe, gets the dependency skipped and the
     * dependent seeder silently writes nothing: `DemoAttachmentsSeeder` looks
     * its owner up by subject, finds no ticket, and reports success having
     * attached nothing.
     *
     * Asking the database is the question that actually matters, and it costs
     * one `exists()`.
     *
     * @param  class-string<Seeder>  $dependency
     * @param  callable(): bool  $satisfied
     */
    public static function needs(Seeder $seeder, string $dependency, callable $satisfied): void
    {
        if ($satisfied()) {
            return;
        }

        $seeder->call($dependency);
    }
}
