<?php

declare(strict_types=1);

namespace App\Modules\Security\Listeners;

use App\Modules\Security\Events\StaffAuthAttempted;
use Illuminate\Support\Facades\Log;

/**
 * Writes sign-in outcomes to the audit channel.
 *
 * Kept alongside RecordStaffAuthAttempt, which writes the same fact to the
 * audit table. Not redundant: this stream is sized for operations and rotates,
 * while the table is what an incident review reads months later. One event
 * feeds both, so they cannot disagree about what happened.
 */
final class LogStaffAuthAttempt
{
    public function handle(StaffAuthAttempted $event): void
    {
        Log::channel('audit')->info('staff.auth.attempted', [
            'email' => $event->email,
            'outcome' => $event->outcome,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
            'actor_id' => $event->userId,
        ]);
    }
}
