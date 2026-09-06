<?php

declare(strict_types=1);

namespace App\Modules\Channels\Console\Commands;

use App\Modules\Channels\Domain\Chat\AbandonStaleConversations;
use Illuminate\Console\Command;

/**
 * Minutely. A conversation the visitor walked away from should leave the
 * waiting list soon enough that an agent does not spend their afternoon
 * answering an empty tab.
 */
final class ChatSweepCommand extends Command
{
    protected $signature = 'chat:sweep';

    protected $description = 'Give up on chat conversations nobody has spoken in. Idempotent: a second run does nothing.';

    public function handle(AbandonStaleConversations $sweep): int
    {
        $count = $sweep->run();

        $this->info($count === 0 ? 'Nothing stale.' : "Gave up on {$count} conversation(s).");

        return self::SUCCESS;
    }
}
