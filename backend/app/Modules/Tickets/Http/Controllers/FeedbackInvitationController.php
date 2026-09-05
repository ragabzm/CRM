<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Contracts\CustomerRequestGateway;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\RateTicket;
use App\Modules\Tickets\Domain\Feedback\FeedbackInvitation;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The customer answered from their inbox.
 *
 * Reached only through a signed link this application issued and emailed to
 * the address on the ticket. The `signed` middleware is the whole
 * authorisation: it proves the link came from us, has not been edited, and has
 * not expired. There is no session here and none is created — following this
 * link grants nothing except recording one answer about one ticket.
 *
 * GET is the tap in the email, and it records the rating immediately, because
 * a link that lands on a page saying "now press the button you already pressed"
 * is not one tap. It then sends the customer to the thank-you page, which
 * offers the optional comment AFTER the answer, never before.
 *
 * POST is that page sending the comment back. Same URL, same signature, so no
 * second credential has to be minted and handed to a browser.
 */
final class FeedbackInvitationController
{
    public function __construct(private readonly RateTicket $rate) {}

    public function __invoke(Request $request, string $ticket, string $verdict): RedirectResponse|JsonResponse
    {
        if (! in_array($verdict, [FeedbackInvitation::UP, FeedbackInvitation::DOWN], true)) {
            /*
             * Unreachable through a valid signature — the verdict is inside the
             * signed payload — and checked anyway, because the day the route
             * changes shape this is the line that fails loudly instead of
             * recording a thumbs-down for every unrecognised word.
             */
            throw ProblemException::make(
                'tickets.unknown_verdict',
                'Unknown answer',
                422,
                'A rating is thumbs up or thumbs down.',
            );
        }

        $positive = FeedbackInvitation::isPositive($verdict);

        $comment = $request->isMethod('POST')
            ? $this->validComment($request)
            : null;

        $row = Ticket::query()->whereKey($ticket)->first();

        if ($row === null) {
            throw ProblemException::make(
                'tickets.not_found',
                'Request not found',
                404,
                'That request no longer exists.',
            );
        }

        /*
         * A GET must not wipe a comment the customer already wrote.
         *
         * Somebody who rated, wrote a sentence, then tapped the same link again
         * from the email meant "yes, that answer" — not "delete what I said".
         * `RateTicket` treats a null comment as a clear, which is right when
         * the customer emptied the box and wrong here, so the existing one is
         * carried forward.
         */
        if ($request->isMethod('GET')) {
            $comment = $row->satisfaction_comment;
        }

        $this->rate->handle(
            Actor::customer((string) $row->customer_id, $this->customerName((string) $row->customer_id)),
            $ticket,
            $positive,
            $comment,
        );

        if ($request->isMethod('POST')) {
            return new JsonResponse([
                'satisfaction' => $positive,
                'satisfaction_comment' => $comment,
            ]);
        }

        return redirect()->away($this->thankYouUrl($request, $ticket, $verdict));
    }

    /**
     * Where the customer lands after the tap.
     *
     * The signature travels with them, so the page can offer the comment box
     * and post it back to this same route. It is already in their address bar
     * — it arrived there from the email — so carrying it forward exposes
     * nothing that was not already exposed.
     */
    private function thankYouUrl(Request $request, string $ticket, string $verdict): string
    {
        return sprintf(
            '%s/portal/feedback?ticket=%s&verdict=%s&expires=%s&signature=%s',
            rtrim((string) config('app.frontend_url'), '/'),
            urlencode($ticket),
            urlencode($verdict),
            urlencode((string) $request->query('expires')),
            urlencode((string) $request->query('signature')),
        );
    }

    private function validComment(Request $request): ?string
    {
        $validated = $request->validate([
            // Optional, always. Never a required field: rating alone is a
            // complete answer and the story says so twice.
            'comment' => ['nullable', 'string', 'max:'.CustomerRequestGateway::MAXIMUM_COMMENT],
        ]);

        $comment = $validated['comment'] ?? null;

        return is_string($comment) ? $comment : null;
    }

    /**
     * The name to record against the rating.
     *
     * Read through the query builder rather than the Customers aggregate: this
     * is a label for the history, and depending on that module's model would
     * make the rating break whenever the model changed.
     */
    private function customerName(string $customerId): string
    {
        $name = DB::table('customers')->where('id', $customerId)->value('full_name');

        return is_string($name) && trim($name) !== '' ? $name : 'Customer';
    }
}
