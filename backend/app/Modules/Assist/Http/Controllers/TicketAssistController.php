<?php

declare(strict_types=1);

namespace App\Modules\Assist\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assist\Domain\TicketAssists;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Query\TicketVisibility;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the machine offers about one ticket, and nothing it does.
 *
 * FOUR READS AND NO WRITE. There is no accept endpoint, no apply endpoint and
 * no send endpoint — confirming a proposed category is the ordinary ticket
 * PATCH an agent already uses, carrying the version and their own name, and
 * sending a drafted reply is the ordinary message POST from the composer they
 * just edited. A route here that applied something would be the automation
 * this whole epic refuses.
 *
 * ABSENCE IS THE FAILURE MODE. Every method answers 200 with an empty result
 * when the capability is off, transmission is disabled, or the provider did
 * not reply — never an error and never a retry hint. The screen above renders
 * complete without the panel, which is what makes the whole product correct
 * with AI switched off.
 */
final class TicketAssistController extends Controller
{
    public function __construct(private readonly TicketAssists $assists) {}

    /**
     * @response array{data: array{summary: string|null}}
     */
    public function summary(Request $request, string $ticket): JsonResponse
    {
        $this->readable($request, $ticket);

        return new JsonResponse([
            'data' => ['summary' => $this->assists->summarise($ticket, $this->locale())],
        ]);
    }

    /**
     * @response array{data: array{suggestions: list<string>}}
     */
    public function reply(Request $request, string $ticket): JsonResponse
    {
        $this->readable($request, $ticket);

        return new JsonResponse([
            'data' => ['suggestions' => $this->assists->suggestReply($ticket, $this->locale())],
        ]);
    }

    /**
     * @response array{data: array{proposal: array{category_id:int,name:string}|null}}
     */
    public function category(Request $request, string $ticket): JsonResponse
    {
        $this->readable($request, $ticket);

        /*
         * A PROPOSAL, beside the field. It is not written anywhere and the
         * response carries no instruction to apply it — a pre-filled field is
         * an application, and applications are what this story refuses.
         */
        return new JsonResponse([
            'data' => ['proposal' => $this->assists->proposeCategory($ticket, $this->locale())],
        ]);
    }

    /**
     * @response array{data: array{articles: list<array<string,mixed>>}}
     */
    public function articles(Request $request, string $ticket): JsonResponse
    {
        $this->readable($request, $ticket);

        return new JsonResponse([
            'data' => ['articles' => $this->assists->suggestArticles($ticket, $this->locale())],
        ]);
    }

    /**
     * Refuses unless this person may actually read this ticket.
     *
     * TWO CHECKS, and the first version of this controller had neither.
     *
     * The capability gate on the route says "may read tickets" — it does not
     * say WHICH, because which rows is a query question and is answered by
     * `TicketVisibility` at each query site. Without it here, an agent could
     * ask for a summary of a ticket the ticket screen would never have shown
     * them, and the answer would arrive with the conversation in it.
     *
     * And a CUSTOMER holds `ticket.read` too, capped to their own tickets.
     * These assists are a staff surface — internal articles are in scope for
     * them by design — so a customer is refused outright rather than served a
     * narrower version. Nothing in this story reaches a customer surface.
     */
    private function readable(Request $request, string $ticketId): void
    {
        $actor = $request->user();

        $isStaff = $actor !== null
            && method_exists($actor, 'hasAnyRole')
            && $actor->hasAnyRole(['administrator', 'supervisor', 'agent']);

        $visible = $actor === null
            ? false
            : TicketVisibility::scopeForActor(Ticket::query()->whereKey($ticketId), $actor)->exists();

        if (! $isStaff || ! $visible) {
            /*
             * 404 rather than 403 when the ticket is simply not theirs: a 403
             * confirms the id exists, and these ids are guessable enough to
             * map a business with.
             */
            throw ProblemException::make(
                $isStaff ? 'tickets.not_found' : 'security.forbidden',
                $isStaff ? 'Ticket not found' : 'These suggestions are for staff',
                $isStaff ? 404 : 403,
                $isStaff
                    ? 'That ticket does not exist, or it is not one you can read.'
                    : 'The assistant works on the agent workspace. Ask the person handling your request.',
            );
        }
    }

    private function locale(): string
    {
        return app()->getLocale() === 'ar' ? 'ar' : 'en';
    }
}
