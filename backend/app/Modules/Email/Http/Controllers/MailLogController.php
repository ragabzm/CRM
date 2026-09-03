<?php

declare(strict_types=1);

namespace App\Modules\Email\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Email\Domain\MailLogEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the mail channel has been doing.
 *
 * Read-only, newest first. The question it answers is "did that reply actually
 * leave?" — asked by an agent met with silence, and by an administrator whose
 * provider has started throttling.
 */
final class MailLogController extends Controller
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    /**
     * @response array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function index(Request $request): JsonResponse
    {
        $query = MailLogEntry::query()->orderByDesc('occurred_at')->orderByDesc('id');

        if (is_string($direction = $request->query('direction')) && $direction !== '') {
            $query->where('direction', $direction);
        }

        if (is_string($status = $request->query('status')) && $status !== '') {
            // The filter that matters: "show me what did not go out".
            $query->where('status', $status);
        }

        $page = $query->paginate(min(
            max((int) $request->query('per_page', (string) self::DEFAULT_LIMIT), 1),
            self::MAX_LIMIT,
        ));

        return new JsonResponse([
            'data' => array_map(
                static fn (MailLogEntry $entry): array => [
                    'id' => (string) $entry->getKey(),
                    'direction' => $entry->direction,
                    'provider' => $entry->provider,
                    'address' => $entry->address,
                    'subject' => $entry->subject,
                    'status' => $entry->status,
                    'attempt' => $entry->attempt,
                    'duration_ms' => $entry->duration_ms,
                    // The provider's own diagnosis. A generic failure message
                    // gives an administrator nothing to act on.
                    'error' => $entry->error,
                    'provider_code' => $entry->provider_code,
                    'ticket_id' => $entry->ticket_id,
                    'message_id' => $entry->message_id,
                    'occurred_at' => $entry->occurred_at?->toIso8601ZuluString(),
                ],
                $page->items(),
            ),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
