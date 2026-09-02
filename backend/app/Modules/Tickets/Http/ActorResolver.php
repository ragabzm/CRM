<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use Illuminate\Http\Request;

/**
 * Turns the current request into an Actor.
 *
 * The ONLY place ambient auth is read. Controllers call this once and pass the
 * result down; the domain never sees a guard, which is what lets the same
 * commands run from a queue worker with `Actor::system(...)`.
 */
final class ActorResolver
{
    /**
     * The actor for a STAFF route.
     *
     * Named for the population rather than "whichever guard answers first": a
     * request can legitimately carry both cookies — a support engineer with a
     * portal account of their own — and letting precedence decide would
     * attribute their action to the wrong identity.
     */
    public function fromRequest(Request $request): Actor
    {
        $staff = $request->user();

        if ($staff !== null) {
            $name = $staff->getAttribute('name');

            return Actor::staff(
                (string) $staff->getAuthIdentifier(),
                is_string($name) && $name !== '' ? $name : 'Staff',
            );
        }

        /*
         * No guard matched. This should be unreachable behind auth middleware —
         * and it throws rather than falling back to a system actor, because
         * silently attributing a person's action to "the system" would put an
         * unexplained change in a customer's history.
         */
        throw $this->unresolved();
    }

    /** The actor for a PORTAL route. Only the portal guard is consulted. */
    public function portalFromRequest(Request $request): Actor
    {
        $account = $request->user('portal');

        if ($account === null) {
            throw $this->unresolved();
        }

        $name = $account->getAttribute('name') ?? $account->getAttribute('email');

        return Actor::portal(
            (string) $account->getAuthIdentifier(),
            is_string($name) && $name !== '' ? $name : 'Customer',
        );
    }

    private function unresolved(): ProblemException
    {
        /*
         * Throws rather than falling back to a system actor: silently
         * attributing a person's action to "the system" would put an
         * unexplained change in a customer's history.
         */
        return ProblemException::make(
            'tickets.actor_unresolved',
            'Could not identify who is acting',
            401,
            'Sign in again and retry.',
        );
    }
}
