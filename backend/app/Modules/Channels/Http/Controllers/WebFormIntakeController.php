<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Channels\Adapters\WebFormChannelAdapter;
use App\Modules\Channels\Domain\Intake\InboundIntake;
use App\Modules\Channels\Http\Requests\SubmitWebFormRequest;
use App\Modules\Platform\Exceptions\ProblemException;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Where a person with no account asks for help.
 *
 * Unauthenticated by design — requiring an account to report a problem is a
 * support desk that only helps people who already got in. What stands in for
 * authentication is three cheap checks that cost a person nothing and cost a
 * script something: a honeypot field, a minimum fill time, and two rate limits.
 *
 * None of them ever returns a blank page. A refusal a person cannot read is
 * indistinguishable from a broken form, and they will simply leave.
 */
final class WebFormIntakeController extends Controller
{
    /**
     * How fast a submission has to be before we stop believing a person made it.
     *
     * Three seconds is below what it takes to read six labels, let alone fill
     * them in. It is deliberately not longer: a person who tabs through a form
     * they have filled in before is fast, and refusing them would be worse
     * than accepting a robot.
     */
    private const MINIMUM_FILL_SECONDS = 3;

    /** How long a draft upload token is good for. */
    private const SESSION_TTL_MINUTES = 60;

    public function __construct(
        private readonly InboundIntake $intake,
        private readonly WebFormChannelAdapter $adapter,
    ) {}

    /**
     * A token anonymous uploads hang off, issued when the page is drawn.
     *
     * The form needs somewhere to put a file before the ticket that will own it
     * exists. Without a token the alternative is an upload endpoint that
     * accepts anything from anyone, which is a public file store.
     *
     * The categories come back with it, because the form needs them and the
     * authenticated `/ticket-categories` endpoint is gated on `ticket.read` —
     * which a stranger does not have. One public call rather than a second
     * public endpoint duplicating the same list.
     *
     * @response array{session_token: string, expires_at: string, categories: array<int, array{id: int, name: string}>}
     */
    public function session(): JsonResponse
    {
        $token = (string) Str::ulid();
        $expiresAt = now()->addMinutes(self::SESSION_TTL_MINUTES);

        DB::table('web_form_sessions')->insert([
            'id' => $token,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The reader's own language, from Accept-Language, the same way every
        // other localised list on this API is chosen.
        $column = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        $categories = DB::table('ticket_categories')
            ->orderBy('sort_order')
            ->orderBy($column)
            ->get(['id', $column.' as name'])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();

        return new JsonResponse([
            'session_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'categories' => $categories,
        ], 201);
    }

    /**
     * @response array{status: string, reference?: string}
     */
    public function store(SubmitWebFormRequest $request): JsonResponse
    {
        /*
         * The honeypot, answered with 202 and a normal-looking body.
         *
         * Telling a robot it was detected teaches whoever wrote it to stop
         * filling the field in. Accepting the submission and doing nothing with
         * it costs them a request and tells them nothing.
         */
        if (trim((string) $request->input('hp_company', '')) !== '') {
            return new JsonResponse(['status' => 'accepted'], 202);
        }

        $this->assertChannelIsOpen();
        $this->assertNotTooFast($request);
        $this->assertOwnsAttachments($request);

        $result = $this->intake->handle($this->adapter->parse($request->validated()));

        $reference = $result['ticket_id'] === null
            ? null
            : DB::table('tickets')->where('id', $result['ticket_id'])->value('reference');

        return new JsonResponse([
            'status' => $result['status'],
            'reference' => $reference === null ? null : (string) $reference,
        ], $result['status'] === 'accepted' ? 201 : 200);
    }

    /**
     * A disabled channel takes nothing new.
     *
     * 403 and a named reason, not a 404: the form is a page a person is
     * looking at, and "this is switched off" is something they can act on by
     * calling instead. Tickets already raised through the account are
     * untouched — disabling is a decision about the front door, not about the
     * people already inside.
     */
    private function assertChannelIsOpen(): void
    {
        if (WebFormChannelAdapter::accountId() !== null) {
            return;
        }

        throw ProblemException::make(
            'channels.channel_disabled',
            'This form is not accepting messages',
            403,
            'The web form has been switched off. Please contact support another way.',
        );
    }

    private function assertNotTooFast(SubmitWebFormRequest $request): void
    {
        $renderedAt = CarbonImmutable::parse((string) $request->input('rendered_at'));

        if ($renderedAt->diffInSeconds(now(), absolute: true) >= self::MINIMUM_FILL_SECONDS) {
            return;
        }

        throw ProblemException::make(
            'channels.web_form_too_fast',
            'That was too quick',
            422,
            'The form was submitted faster than it can be filled in. Please take a moment and try again.',
        );
    }

    /**
     * Attachments must belong to the draft this submission names.
     *
     * An attachment id supplied by a caller is not evidence they own the file.
     * Without this check, anybody who could guess an id could staple somebody
     * else's upload to their own ticket.
     */
    private function assertOwnsAttachments(SubmitWebFormRequest $request): void
    {
        $ids = (array) $request->input('attachment_ids', []);

        if ($ids === []) {
            return;
        }

        $token = (string) $request->input('session_token', '');

        $live = DB::table('web_form_sessions')
            ->where('id', $token)
            ->where('expires_at', '>', now())
            ->exists();

        if (! $live) {
            throw ProblemException::make(
                'channels.web_form_session_expired',
                'This form has been open too long',
                422,
                'Please reload the page and attach your files again.',
            );
        }

        $owned = DB::table('attachments')
            ->whereIn('id', $ids)
            ->where('owner_type', 'web_form_draft')
            ->where('owner_id', $token)
            ->count();

        if ($owned !== count($ids)) {
            throw ProblemException::make(
                'channels.web_form_attachment_unknown',
                'An attachment could not be matched',
                422,
                'Please reload the page and attach your files again.',
            );
        }
    }
}
