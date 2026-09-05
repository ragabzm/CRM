<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Console\Commands;

use App\Modules\Tickets\Domain\Personal\ReminderSweep;
use Illuminate\Console\Command;

/**
 * Minutely. A reminder set for 09:00 that arrives at 09:15 is a reminder
 * somebody stopped trusting, so the resolution of the whole feature is the
 * interval this runs on.
 */
final class RemindersSweepCommand extends Command
{
    protected $signature = 'reminders:sweep';

    protected $description = 'Send the reminders whose moment has come. Idempotent: a second run sends nothing.';

    public function handle(ReminderSweep $sweep): int
    {
        $fired = $sweep->run();

        $this->info($fired === 0 ? 'Nothing due.' : "Fired {$fired} reminder(s).");

        return self::SUCCESS;
    }
}
