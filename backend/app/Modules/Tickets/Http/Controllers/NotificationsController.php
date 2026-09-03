<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the bell reads.
 *
 * Read + unread mingled, newest first — deliberately one list rather than two
 * tabs. Somebody checking the bell wants to know what happened, and splitting
 * that into "new" and "everything" makes them look in two places for one
 * answer.
 */
final class NotificationsController extends Controller
{
    /** Enough to fill the dropdown without becoming a page of its own. */
    private const LIMIT = 20;

    /**
     * @response array{data: array<int, array<string, mixed>>, unread_count: int}
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            throw ProblemException::make('platform.unauthorized', 'Authentication required.', 401, 'Sign in first.');
        }

        $notifications = $user->notifications()->limit(self::LIMIT)->get();

        return new JsonResponse([
            'data' => $notifications->map(fn ($notification): array => [
                'id' => (string) $notification->id,
                /*
                 * The text as it was written, in the language the recipient had
                 * at the time. Not re-translated on read: a notification is a
                 * record of something that was said to this person, and it
                 * should not change wording because they later switched
                 * language.
                 */
                'text' => $notification->data['text'] ?? '',
                'reference' => $notification->data['reference'] ?? null,
                'ticket_id' => $notification->data['ticket_id'] ?? null,
                'kind' => $notification->data['kind'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601ZuluString(),
            ])->all(),

            /*
             * A separate count, not `count($data)`. The list is capped at
             * twenty; a badge that said "20" when there were ninety would be
             * worse than no badge, because it would look precise.
             */
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * @response array{status: string}
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user?->notifications()->whereKey($id)->first();

        if ($notification === null) {
            /*
             * 404 for somebody else's notification, not 403. A 403 would
             * confirm the id exists, and these ids are the only thing standing
             * between a curious agent and knowing how many notifications a
             * colleague has.
             */
            throw ProblemException::make(
                'notifications.not_found',
                'Notification not found',
                404,
                'No notification with that id belongs to you.',
            );
        }

        // Idempotent: a second click on an already-read item is not an error,
        // and treating it as one would surface a red banner for nothing.
        $notification->markAsRead();

        return new JsonResponse(['status' => 'read']);
    }
}
