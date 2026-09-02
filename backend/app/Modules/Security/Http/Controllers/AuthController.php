<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Events\StaffAuthAttempted;
use App\Modules\Security\Http\Requests\LoginRequest;
use App\Models\User;
use App\Modules\Security\Http\StaffUserShape;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Staff sign-in, over Sanctum's SPA cookie mode.
 *
 * No token is ever issued. The session lives in an http-only cookie the browser
 * sends automatically and JavaScript cannot read, which is what makes "no
 * credential is ever handled by client JavaScript" true by construction rather
 * than by discipline.
 */
final class AuthController extends Controller
{
    /**
     * Sign in.
     *
     * @response array{id: int, name: string, email: string, preferred_locale: string, roles: string[]}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            event(StaffAuthAttempted::failure(
                (string) $credentials['email'],
                $request->ip(),
                $request->userAgent(),
            ));

            /*
             * One message for "no such account" and "wrong password" alike.
             * Distinguishing them turns the login form into an account
             * enumeration oracle.
             */
            throw ProblemException::make(
                'security.invalid_credentials',
                'Sign-in failed.',
                401,
                __('auth.failed'),
            );
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        /*
         * Credentials were right, but the account is switched off.
         *
         * Refused HERE rather than left to EnsureActiveUser on the next
         * request: otherwise a deactivated account is handed a real session
         * cookie it can never use, which looks to the person like an
         * intermittent fault rather than a decision someone made.
         *
         * Naming the reason is safe — the caller has already proved they hold
         * the credentials, so this discloses nothing they did not know.
         */
        if (! $user->isActive()) {
            Auth::guard('web')->logout();

            event(StaffAuthAttempted::failure(
                $user->email,
                $request->ip(),
                $request->userAgent(),
            ));

            throw ProblemException::make(
                'security.account_deactivated',
                'Account deactivated',
                401,
                __('authorization.deactivated'),
                ['contact' => 'administrator'],
            );
        }

        // A new session id after privilege change, so a fixated session id
        // captured before sign-in is worthless afterwards.
        $request->session()->regenerate();

        event(StaffAuthAttempted::success(
            $user->email,
            $request->ip(),
            $request->userAgent(),
            (string) $user->getAuthIdentifier(),
        ));

        return new JsonResponse($this->profileShape($user));
    }

    /**
     * Sign out and destroy the session.
     *
     * @response array{status: string}
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        // Invalidate, then re-issue a token: the old session id must not remain
        // usable, and the next request still needs a valid CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * The signed-in staff member.
     *
     * @response array{id: int, name: string, email: string, preferred_locale: string, roles: string[]}
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse($this->profileShape($user));
    }

    /**
     * Session timing, so the frontend can warn before a session lapses rather
     * than discovering it on the next request.
     *
     * @response array{inactivity_minutes: int, authenticated: bool}
     */
    public function session(Request $request): JsonResponse
    {
        return new JsonResponse([
            'inactivity_minutes' => (int) config('auth.staff.inactivity_minutes'),
            'authenticated' => Auth::guard('web')->check(),
        ]);
    }

    /**
     * @return array{id: int, name: string, email: string, preferred_locale: string, roles: list<string>}
     */
    private function profileShape(User $user): array
    {
        return StaffUserShape::for($user);
    }
}
