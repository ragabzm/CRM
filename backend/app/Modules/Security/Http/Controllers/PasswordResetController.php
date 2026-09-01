<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Http\Requests\ForgotPasswordRequest;
use App\Modules\Security\Http\Requests\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Password reset, on Laravel's password broker.
 *
 * The broker is used rather than hand-rolled because it already gets the three
 * properties that matter right: the token is hashed at rest, single-use, and
 * expires. Reimplementing any of those is how reset flows become the weakest
 * part of an otherwise sound authentication story.
 */
final class PasswordResetController extends Controller
{
    /**
     * Request a reset link.
     *
     * @response array{status: string}
     */
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');

        $status = Password::broker('users')->sendResetLink(['email' => $email]);

        /*
         * ALWAYS 202, whatever the broker said.
         *
         * Returning 404 for an unknown address turns this endpoint into an
         * account-enumeration oracle: anyone could test a list of emails against
         * it and learn who works here. The throttle response is folded in for
         * the same reason — "you asked too recently" also confirms the account
         * exists.
         */
        if ($status !== Password::RESET_LINK_SENT && $status !== Password::RESET_THROTTLED) {
            // A genuine failure (mail transport down) is worth knowing about,
            // but still must not change what the caller sees.
            report(new \RuntimeException("Password reset link not sent: {$status}"));
        }

        return new JsonResponse(['status' => 'accepted'], 202);
    }

    /**
     * Redeem a reset token.
     *
     * @response array{status: string}
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    // Invalidates "remember me" cookies minted before the reset:
                    // if the reset was prompted by a compromise, the attacker's
                    // persistent cookie must not survive it.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return new JsonResponse(['status' => 'ok'], 200);
        }

        /*
         * Expired, already used, and never valid all map to the same code. The
         * distinction is not actionable for the reader — they need a new link
         * either way — and telling them which one leaks whether a token ever
         * existed.
         */
        throw ProblemException::make(
            'security.reset_token_invalid',
            'Reset link is no longer valid.',
            422,
            __('passwords.token'),
        );
    }
}
