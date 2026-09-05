<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Middleware;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Domain\Capabilities;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side refusal, on every request.
 *
 * Hiding a control in the UI is not enforcement — it is a suggestion that holds
 * only for people using the UI. This middleware is what actually refuses, and
 * it refuses with a body that says WHAT was refused and WHO to ask, because a
 * bare 403 leaves the reader with no next step and generates a support ticket.
 */
final class RequireCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        /*
         * A typo in a route's middleware string would otherwise fail OPEN —
         * `can.capability:tickets.reasign` names a capability nobody holds, so
         * it refuses everyone, which looks like working security until an
         * administrator reports being locked out. Failing loudly here, plus
         * CapabilitiesInSyncTest, makes the typo impossible to ship.
         */
        if (! Capabilities::exists($capability)) {
            throw new InvalidArgumentException(
                "Unknown capability [{$capability}]. Add it to ".Capabilities::class.'.',
            );
        }

        $user = $request->user();

        if ($user === null || ! $this->holds($user, $capability)) {
            throw ProblemException::make(
                'security.forbidden',
                'Forbidden',
                403,
                __('authorization.forbidden', ['capability' => $capability]),
                [
                    // What was refused, and who can grant it.
                    'capability' => $capability,
                    'contact' => 'administrator',
                ],
            );
        }

        return $next($request);
    }

    /**
     * Does this person hold it — including when nobody does yet?
     *
     * `$user->can()` asks spatie, which THROWS when the permission has no row
     * rather than answering false. That is the state between deploying code
     * that declares a new capability and running the seeder that grants it,
     * and it is a state every deployment passes through. Left unhandled it
     * turns the endpoint into a 500: an outage, in the logs as an exception,
     * for what is really "nobody has this yet".
     *
     * A capability nobody holds refuses everybody, which is the correct and
     * safe answer. The typo case — a capability that does not exist in PHP at
     * all — is already refused loudly above, so nothing is being swallowed
     * here that was worth hearing about.
     */
    private function holds(object $user, string $capability): bool
    {
        try {
            return method_exists($user, 'can') && $user->can($capability);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
