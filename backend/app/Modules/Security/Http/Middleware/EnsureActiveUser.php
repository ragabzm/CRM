<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Middleware;

use App\Models\User;
use App\Modules\Platform\Exceptions\ProblemException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivation takes effect on the very next request.
 *
 * Deleting a user's sessions and tokens at deactivation time is necessary but
 * not sufficient: a request already in flight, or one carrying a session this
 * process did not delete, would otherwise sail through until it expired. This
 * check closes that window by asking the question on every request instead of
 * trusting a revocation that happened elsewhere.
 *
 * A deactivated account is 401, not 403: the account cannot act at all, so this
 * is a statement about the session rather than about a missing permission.
 */
final class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            throw ProblemException::make(
                'security.account_deactivated',
                'Account deactivated',
                401,
                __('authorization.deactivated'),
                ['contact' => 'administrator'],
            );
        }

        return $next($request);
    }
}
