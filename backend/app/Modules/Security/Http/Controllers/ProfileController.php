<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Security\Http\Requests\ChangePasswordRequest;
use App\Modules\Security\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Modules\Security\Http\StaffUserShape;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * The staff member's own profile.
 *
 * Scoped to the authenticated user throughout — there is no id in any route, so
 * there is no object to fail to authorise. Administering *other* people's
 * accounts is Story 2.3 and lives behind its own permissions.
 */
final class ProfileController extends Controller
{
    /**
     * @response array{id: int, name: string, email: string, preferred_locale: string, roles: list<string>}
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return new JsonResponse($this->shape($user));
    }

    /**
     * Update name and language.
     *
     * @response array{id: int, name: string, email: string, preferred_locale: string, roles: list<string>}
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Email is deliberately absent: changing the address someone signs in
        // with is an identity change, needing verification of the new address
        // and notification of the old. That is its own story, not a field on
        // this form.
        $user->fill($request->safe()->only(['name', 'preferred_locale']))->save();

        return new JsonResponse($this->shape($user->refresh()));
    }

    /**
     * Change password.
     *
     * @response array{status: string}
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            'remember_token' => \Illuminate\Support\Str::random(60),
        ])->save();

        /*
         * Keep THIS session valid and invalidate the others: the person who just
         * proved their current password should not be signed out of the tab they
         * are using, while any other session — including an attacker's — dies.
         */
        $request->session()->regenerate();
        \Illuminate\Support\Facades\Auth::guard('web')->logoutOtherDevices(
            (string) $request->validated('password'),
        );

        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * @return array{id: int, name: string, email: string, preferred_locale: string, roles: list<string>}
     */
    private function shape(User $user): array
    {
        return StaffUserShape::for($user);
    }
}
