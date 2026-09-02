<?php

use App\Modules\Tickets\Console\Commands\TicketsAutoCloseCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Close resolved tickets the customer has stopped replying to.
 *
 * Every fifteen minutes. The window itself is measured in hours, so the cadence
 * only decides how promptly a ticket closes once it already qualifies —
 * frequent enough that "it closed on time", cheap enough that the indexed query
 * is noise.
 *
 * withoutOverlapping: a slow run must not get a second one behind it working
 * the same rows. onOneServer: in a multi-node deploy exactly one host sweeps.
 */
Schedule::command(TicketsAutoCloseCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
