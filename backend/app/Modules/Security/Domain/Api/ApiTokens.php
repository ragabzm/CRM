<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Api;

use App\Models\User;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Domain\Capabilities;

/**
 * Credentials for the other systems in this organisation.
 *
 * A token is issued AGAINST A PERSON — a service account an administrator
 * created like any other staff member — and scoped to a subset of what that
 * person can already do. Two consequences, and both are the point:
 *
 *   An API client can reach nothing a role cannot. The gate checks the role
 *   AND the ability, so a mis-typed ability list cannot grant anything.
 *
 *   Everything downstream already works. Visibility, the audit actor, the
 *   ticket commands and the version guard all take a user, and a token client
 *   IS one — so there is no second path through the product for a machine,
 *   which is the whole claim of "it is the same API".
 *
 * ABILITIES ARE CAPABILITY NAMES, from the fixed matrix. Never route names:
 * an ability list that names endpoints becomes a second permission model the
 * moment a route is added, and the two drift the first time somebody splits
 * one endpoint into two.
 */
final class ApiTokens
{
    /**
     * Issues a token and returns the plaintext ONCE.
     *
     * The caller must hand it straight to the response. It is not stored
     * anywhere in readable form, not logged, not audited and not retrievable
     * afterwards — a secret that can be read back is a secret that will be,
     * by somebody with a screenshot.
     *
     * @param  list<string>  $abilities
     * @return array{token: string, id: int, name: string, abilities: list<string>}
     */
    public function issue(User $owner, string $name, array $abilities): array
    {
        $abilities = $this->validate($owner, $abilities);

        $token = $owner->createToken($name, $abilities);

        return [
            'token' => $token->plainTextToken,
            'id' => (int) $token->accessToken->getKey(),
            'name' => $name,
            'abilities' => $abilities,
        ];
    }

    /**
     * Every ability must be a real capability the owner already holds.
     *
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private function validate(User $owner, array $abilities): array
    {
        $abilities = array_values(array_unique(array_filter(
            array_map(static fn (mixed $a): string => trim((string) $a), $abilities),
            static fn (string $a): bool => $a !== '',
        )));

        if ($abilities === []) {
            /*
             * A token with no abilities can do nothing, which sounds harmless
             * and is not: it is a credential in somebody's config file that
             * fails every call for a reason nobody can see from the outside.
             */
            throw ProblemException::make(
                'security.no_abilities',
                'Say what this client may do',
                422,
                'A client needs at least one capability. One that can do nothing is a credential that fails silently.',
            );
        }

        foreach ($abilities as $ability) {
            if (! Capabilities::exists($ability)) {
                throw ProblemException::make(
                    'security.unknown_ability',
                    'That is not a capability',
                    422,
                    sprintf('[%s] is not one of this product\'s capabilities. Abilities are capability names, never endpoint names.', $ability),
                    ['ability' => $ability],
                );
            }

            if (! $owner->can($ability)) {
                /*
                 * Refused at ISSUE, not merely at request time. The gate would
                 * catch it either way, but an administrator who scoped a client
                 * to something its service account cannot do has made a mistake
                 * they should hear about now — not in a support ticket about an
                 * integration that returns 403 for one endpoint.
                 */
                throw ProblemException::make(
                    'security.ability_exceeds_owner',
                    'That is more than this account can do',
                    422,
                    sprintf('The service account does not hold [%s], so a client of it cannot either.', $ability),
                    ['ability' => $ability, 'owner' => $owner->name],
                );
            }
        }

        return $abilities;
    }
}
