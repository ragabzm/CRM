<?php

declare(strict_types=1);

namespace App\Modules\Security\Listeners;

use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Audit\Domain\AuditActorType;
use App\Modules\Security\Events\StaffAuthAttempted;

/**
 * Writes the durable record of a sign-in attempt.
 *
 * Separate from LogStaffAuthAttempt, which writes the same fact to the log
 * stream. They are not redundant: logs rotate and are sized for operations,
 * while this table is queried during an incident review months later. The event
 * feeding both is what keeps them from disagreeing.
 *
 * A listener rather than a call inside AuthController, so the controller stays
 * about authenticating and the auditing cannot be forgotten on a future third
 * path that dispatches the same event.
 */
final class RecordStaffAuthAttempt
{
    public function __construct(private readonly AuditWriter $writer) {}

    public function handle(StaffAuthAttempted $event): void
    {
        $succeeded = $event->outcome === StaffAuthAttempted::OUTCOME_SUCCESS;

        $this->writer->record(
            action: $succeeded ? AuditAction::SignInSucceeded : AuditAction::SignInFailed,
            targetType: 'user',
            targetId: $event->userId,
            /*
             * The email, never anything resembling the credential — the event
             * deliberately carries no password, and this records only what was
             * claimed and where from.
             *
             * On failure the email is the only identifying fact there is, and
             * it is the one a brute-force review needs.
             */
            after: array_filter([
                'email' => $event->email,
                'user_agent' => $event->userAgent,
            ], static fn (mixed $value): bool => $value !== null),
            /*
             * A failed attempt has no confirmed actor: whoever typed that email
             * has not proved they are its owner, and recording them as that
             * user would put a stranger's action under an innocent person's
             * name.
             */
            actorType: $succeeded ? AuditActorType::User : AuditActorType::Guest,
            actorId: $succeeded ? $event->userId : null,
            actorLabel: $succeeded ? null : $event->email,
        );
    }
}
