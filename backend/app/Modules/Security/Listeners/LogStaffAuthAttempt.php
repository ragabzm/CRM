<?php

declare(strict_types=1);

namespace App\Modules\Security\Listeners;

use App\Modules\Security\Events\StaffAuthAttempted;
use Illuminate\Support\Facades\Log;

/**
 * Writes sign-in outcomes to the audit channel.
 *
 * TODO(Story 2.4): replace this listener with the real audit-log writer. The
 * event and its payload are the contract; only this class changes, which is why
 * the controllers dispatch an event rather than calling a logger directly.
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
