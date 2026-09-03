<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Getting back in.
 *
 * Uses the `portal_accounts` broker, which is a different token table from the
 * staff one. A single shared table would mean a token issued for a customer
 * could be spent against a staff account with the same address — and support
 * desks are exactly where one person often has both.
 */
final class PortalPasswordController extends Controller
{
    /**
     * @response array{status: string}
     */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc']]);

        Password::broker('portal_accounts')->sendResetLink([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        /*
         * Always the same answer, whether or not the address has an account.
         *
         * A different response for a known address turns this into a way to
         * discover who is a customer — and this endpoint is deliberately
         * unauthenticated, so anybody can ask.
         */
        return new JsonResponse(['status' => 'sent']);
    }

    /**
     * @response array{status: string}
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->uncompromised()],
        ]);

        $status = Password::broker('portal_accounts')->reset(
            [
                'email' => strtolower(trim((string) $request->input('email'))),
                'password' => (string) $request->input('password'),
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => (string) $request->input('token'),
            ],
            function ($account, string $password): void {
                $account->forceFill([
                    'password' => $password,
                    /*
                     * A new remember token, so a "remember me" cookie taken
                     * before the reset stops working. Somebody resetting a
                     * password is often doing it BECAUSE another person has
                     * their session.
                     */
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($account));
            },
        );

        if ($status !== Password::PasswordReset) {
            /*
             * One message for expired, already-used and forged tokens. Telling
             * them apart would confirm to somebody holding a stale link that it
             * was once real, and for whom.
             *
             * The broker deletes the token on success, which is what makes a
             * link single-use.
             */
            throw ProblemException::make(
                'portal.invalid_reset_token',
                'That link no longer works',
                422,
                'Reset links expire and can only be used once. Ask for a new one.',
            );
        }

        return new JsonResponse(['status' => 'reset']);
    }
}
