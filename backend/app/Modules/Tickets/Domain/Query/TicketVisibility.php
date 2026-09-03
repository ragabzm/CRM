<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Query;

use App\Modules\Security\Domain\Roles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single place the row-level ticket rule lives.
 *
 * "Agent sees own + unassigned; Supervisor and Administrator see all; Customer
 * sees only their own." Every list, export, count and search must run through
 * here — a second copy of this rule is a second thing to get wrong, and the one
 * that is wrong will be the one nobody tested.
 *
 * Deliberately NOT a global Eloquent scope. A global scope applies invisibly,
 * which sounds safer and is worse: it silently filters admin tooling, reports
 * and jobs that legitimately need every row, and the workaround
 * (`withoutGlobalScope`) is a blunt instrument that removes the rule entirely.
 * An explicit call at the query site is greppable and reviewable.
 *
 * Department is NOT considered here. Department is a grouping and a filter; a
 * ticket surfacing outside the caller's department is not a leak.
 */
final class TicketVisibility
{
    /** Column holding the assigned agent. */
    public const ASSIGNEE_COLUMN = 'assignee_id';

    /** Column holding the requesting customer. */
    public const CUSTOMER_COLUMN = 'customer_id';

    /**
     * Narrows a ticket query to the rows this actor may see.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function scopeForActor(Builder $query, Authenticatable $actor): Builder
    {
        if (self::hasRole($actor, Roles::ADMINISTRATOR) || self::hasRole($actor, Roles::SUPERVISOR)) {
            // Everything. Supervision that cannot see the queue is not
            // supervision.
            return $query;
        }

        if (self::hasRole($actor, Roles::AGENT)) {
            /*
             * Own work plus the unassigned pool — the pool is what an agent
             * picks their next ticket from, so excluding it would leave new
             * work invisible to everyone who could action it.
             *
             * Wrapped in a closure so the OR cannot leak out and widen an
             * unrelated WHERE the caller already applied.
             */
            $actorId = $actor->getAuthIdentifier();

            return $query->where(function (Builder $scoped) use ($actorId): void {
                $scoped->where(self::ASSIGNEE_COLUMN, $actorId)
                    ->orWhereNull(self::ASSIGNEE_COLUMN);
            });
        }

        if (self::hasRole($actor, Roles::CUSTOMER)) {
            $customerId = $actor->getAttribute(self::CUSTOMER_COLUMN);

            /*
             * A customer with no linked customer record sees NOTHING rather
             * than everything. `where(column, null)` would match every row with
             * a null customer_id — the classic fail-open.
             */
            return $customerId === null
                ? $query->whereRaw('1 = 0')
                : $query->where(self::CUSTOMER_COLUMN, $customerId);
        }

        // An actor with no known role sees nothing. Failing closed is the only
        // safe default for a rule about who may see what.
        return $query->whereRaw('1 = 0');
    }

    /**
     * Narrows to named people, the unassigned pool, or both.
     *
     * Here rather than in TicketListQuery because this file owns what
     * "unassigned" MEANS. It is not the absence of an assignee filter — it is
     * the pool an agent picks their next ticket from, and it is the same
     * concept the agent rule above uses. A second `orWhereNull('assignee_id')`
     * elsewhere would be a second thing to get wrong, and the wrong one is the
     * one nobody tested. `NoGlobalScopesTest` enforces that there is only one.
     *
     * Wrapped in a closure so the OR cannot escape and widen a WHERE the caller
     * already applied — including the visibility scope itself.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  list<int>  $userIds
     */
    public static function scopeToAssignees(Builder $query, array $userIds, bool $includeUnassigned): Builder
    {
        if ($userIds === [] && ! $includeUnassigned) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($userIds, $includeUnassigned): void {
            if ($userIds !== []) {
                $scoped->whereIn(self::ASSIGNEE_COLUMN, $userIds);
            }

            if (! $includeUnassigned) {
                return;
            }

            $userIds === []
                ? $scoped->whereNull(self::ASSIGNEE_COLUMN)
                : $scoped->orWhereNull(self::ASSIGNEE_COLUMN);
        });
    }

    private static function hasRole(Authenticatable $actor, string $role): bool
    {
        return method_exists($actor, 'hasRole') && $actor->hasRole($role);
    }
}
