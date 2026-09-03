<?php

declare(strict_types=1);

namespace App\Modules\Email\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Email\Domain\Inbound\InboundMailIntake;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The mail nobody could turn into anything.
 *
 * A customer who emails support and hears nothing has been ignored, whatever
 * the technical reason. This list is the only place that fact is visible — and
 * the replay is how a message gets a second chance once the parser that choked
 * on it is fixed.
 */
final class MailQuarantineController extends Controller
{
    public function __construct(
        private readonly InboundMailIntake $intake,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @response array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('mail_quarantine')
            // Outstanding first: the list exists to show what still needs a
            // person.
            ->orderByRaw('resolved_at is null desc')
            ->orderByDesc('received_at');

        if ($request->query('resolved') === 'false') {
            $query->whereNull('resolved_at');
        }

        $page = $query->paginate(min(max((int) $request->query('per_page', '25'), 1), 100));

        return new JsonResponse([
            'data' => array_map(
                static fn (object $row): array => [
                    'id' => (string) $row->id,
                    'provider' => $row->provider,
                    'from_address' => $row->from_address,
                    'subject' => $row->subject,
                    // The parser's own words: the only thing that makes a
                    // failure diagnosable.
                    'reason' => $row->reason,
                    'received_at' => $row->received_at,
                    'resolved_at' => $row->resolved_at,
                    /*
                     * The raw source is NOT in the list.
                     *
                     * It is a customer's entire email — their words, their
                     * address, their attachments — and a list view has no
                     * reason to carry twenty of them. It is fetched
                     * deliberately, one at a time, by somebody diagnosing.
                     */
                    'raw_bytes' => mb_strlen((string) $row->raw),
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

    /**
     * One message, with its bytes.
     *
     * @response array<string, mixed>
     */
    public function show(string $id): JsonResponse
    {
        $row = $this->find($id);

        return new JsonResponse([
            'id' => (string) $row->id,
            'provider' => $row->provider,
            'from_address' => $row->from_address,
            'subject' => $row->subject,
            'reason' => $row->reason,
            // A parser bug is only diagnosable against the bytes that broke it.
            'raw' => $row->raw,
            'received_at' => $row->received_at,
            'resolved_at' => $row->resolved_at,
        ]);
    }

    /**
     * Feeds the message back through intake.
     *
     * @response array<string, mixed>
     */
    public function replay(string $id): JsonResponse
    {
        $row = $this->find($id);

        if ($row->resolved_at !== null) {
            throw ProblemException::make(
                'email.already_resolved',
                'This message has already been handled',
                409,
                'Replaying it again would create a second ticket for one email.',
            );
        }

        /*
         * A NEW external id, deliberately.
         *
         * The original was claimed when the message first arrived, so a replay
         * under it would be recognised as a duplicate and do nothing — which
         * looks exactly like a replay that silently failed. Prefixing makes the
         * second attempt its own event, and leaves the first one's record
         * intact as the evidence that it happened.
         */
        $result = $this->intake->accept(
            (string) $row->raw,
            (string) $row->provider,
            'replay:'.$row->id,
        );

        DB::table('mail_quarantine')->where('id', $id)->update([
            'resolved_at' => now(),
            'resolved_by' => (string) (request()->user()?->getAuthIdentifier() ?? ''),
            'updated_at' => now(),
        ]);

        return new JsonResponse($result);
    }

    private function find(string $id): object
    {
        $row = DB::table('mail_quarantine')->where('id', $id)->first();

        if ($row === null) {
            throw ProblemException::make(
                'email.quarantine_not_found',
                'Message not found',
                404,
                'No quarantined message with that id.',
            );
        }

        return $row;
    }
}
