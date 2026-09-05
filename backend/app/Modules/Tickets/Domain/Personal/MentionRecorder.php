<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal;

use App\Models\User;
use App\Modules\Tickets\Notifications\MentionedInNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records who a note named, and tells them.
 *
 * Two steps that must not be one. The ROWS are written inside the note's own
 * transaction, because "this note named these people" is part of the note; the
 * NOTIFICATIONS are sent afterwards, because an unreachable mail queue must
 * not roll back a colleague's note.
 */
final class MentionRecorder
{
    public function __construct(private readonly MentionParser $parser) {}

    /**
     * Resolves the body and stores the mentions. Returns the user ids named.
     *
     * @return list<int>
     */
    public function record(string $messageId, string $ticketId, string $body, ?string $authorId): array
    {
        $userIds = $this->parser->resolve($body);

        if ($authorId !== null) {
            /*
             * Naming yourself in your own note is not a notification.
             *
             * People do it while writing — "@me: chase this" — and a system
             * that emails you your own words teaches you to ignore the one
             * that came from somebody else.
             */
            $userIds = array_values(array_filter(
                $userIds,
                static fn (int $id): bool => (string) $id !== $authorId,
            ));
        }

        foreach ($userIds as $userId) {
            // `forceFill`, like every other write in this module: nothing is
            // mass-assignable, so a stray `create($request->all())` cannot put
            // somebody else's id in `user_id`.
            $mention = new NoteMention;

            $mention->forceFill([
                'message_id' => $messageId,
                'user_id' => $userId,
                'ticket_id' => $ticketId,
                'read_at' => null,
            ])->save();
        }

        return $userIds;
    }

    /**
     * @param  list<int>  $userIds
     */
    public function notify(array $userIds, string $ticketId, string $messageId, string $actorName): void
    {
        if ($userIds === []) {
            return;
        }

        try {
            $ticket = DB::table('tickets')->where('id', $ticketId)->first(['reference', 'subject']);

            if ($ticket === null) {
                return;
            }

            $recipients = User::query()
                ->whereIn('id', $userIds)
                // Belt and braces: the parser already refuses a deactivated
                // name, and somebody can be deactivated between the note being
                // written and the queue running.
                ->where('is_active', true)
                ->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new MentionedInNote(
                    $ticketId,
                    (string) $ticket->reference,
                    (string) $ticket->subject,
                    $actorName,
                    $messageId,
                ));
            }
        } catch (Throwable $e) {
            /*
             * A notification is a courtesy on top of a note that is already
             * written. Refusing the note because the courtesy failed would be
             * the wrong way round.
             */
            Log::warning('Could not send a mention notification.', [
                'message_id' => $messageId,
                'reason' => $e->getMessage(),
                'consequence' => 'The note and its mention rows are unaffected; nobody was told.',
            ]);
        }
    }
}
