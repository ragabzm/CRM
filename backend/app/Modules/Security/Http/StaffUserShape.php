<?php

declare(strict_types=1);

namespace App\Modules\Security\Http;

use App\Models\User;

/**
 * The one public shape of a staff user.
 *
 * Built explicitly rather than by serialising the model: a hidden-attribute
 * list is a denylist, and a denylist is one migration away from leaking a
 * column nobody remembered to hide.
 *
 * It lives here rather than as a private method on each controller because it
 * was written twice — once in AuthController, once in ProfileController — and
 * the two had already drifted: sign-in reported `roles`, `GET /profile` did
 * not, so the SPA saw a user's roles appear and then vanish depending on which
 * call it had made last.
 */
final class StaffUserShape
{
    /**
     * @return array{id: int, name: string, email: string, preferred_locale: string, roles: list<string>}
     */
    public static function for(User $user): array
    {
        return [
            'id' => (int) $user->getAuthIdentifier(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'preferred_locale' => $user->preferredLocale(),
            /*
             * The role names, so the SPA can avoid advertising a destination
             * the server will refuse. Presentation only — every endpoint
             * re-checks the capability server-side, because a client that lies
             * about its roles must gain nothing by it.
             */
            'roles' => $user->getRoleNames()->values()->all(),
        ];
    }
}
