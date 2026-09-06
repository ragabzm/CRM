<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Domain\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The sole writer of roles and permissions.
 *
 * There is no HTTP endpoint that creates, edits or deletes either — the matrix
 * below is the whole authorization model, reviewable in one screen and
 * identical in every environment. Changing it is a code change with a diff, not
 * a click in production that nobody can reconstruct afterwards.
 *
 * Idempotent: safe to re-run, because `firstOrCreate` + `syncPermissions`
 * converge rather than accumulate.
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The fixed matrix.
     *
     * Administrator is deliberately ABSENT: it holds everything through a
     * Gate::before in SecurityServiceProvider, so a capability added later
     * cannot accidentally be one an administrator lacks.
     *
     * @var array<string, list<string>>
     */
    private const MATRIX = [
        Roles::SUPERVISOR => [
            Capabilities::ROLE_READ,
            Capabilities::DEPARTMENT_READ,
            Capabilities::TICKET_READ,
            Capabilities::TICKET_CREATE,
            Capabilities::TICKET_UPDATE,
            Capabilities::TICKET_REASSIGN,
            Capabilities::TICKET_CLOSE,
            Capabilities::TICKET_ASSIGN,
            // Only a supervisor may take a ticket out of a colleague's hands.
            Capabilities::TICKET_REASSIGN_ANY,
            Capabilities::TICKET_CHANGE_STATUS,
            Capabilities::TICKET_CHANGE_DEPARTMENT,
            Capabilities::TICKET_RESOLVE,
            Capabilities::TICKET_REOPEN,
            Capabilities::TICKET_ESCALATE,
            Capabilities::CUSTOMER_READ,
            Capabilities::CUSTOMER_MANAGE,

            /*
             * A supervisor writes AND publishes.
             *
             * The story says "an authorised user publishes directly" and gives
             * no approval step, so somebody below administrator has to hold
             * this or the lifecycle stalls on one person's availability. A
             * supervisor already decides what a customer is told on a ticket;
             * deciding what the same answer says in an article is the same
             * judgement.
             */
            Capabilities::KNOWLEDGE_VIEW,
            Capabilities::KNOWLEDGE_MANAGE,
            Capabilities::KNOWLEDGE_PUBLISH,

            Capabilities::CHAT_HANDLE,

            /*
             * The reports stop here. An agent has a queue and a home screen
             * with live counts; a supervisor is the person asked how the month
             * went, and this is the capability that question needs.
             */
            Capabilities::REPORT_VIEW,
            Capabilities::BRANCH_READ,
        ],
        Roles::AGENT => [
            Capabilities::DEPARTMENT_READ,
            Capabilities::TICKET_READ,
            Capabilities::TICKET_CREATE,
            Capabilities::TICKET_UPDATE,
            Capabilities::TICKET_CLOSE,
            Capabilities::TICKET_ASSIGN,

            /*
             * An agent raises a hand.
             *
             * They are the person who can actually see the trouble — a
             * supervisor who has to notice it themselves has already lost the
             * time escalating was meant to save. It costs nothing to be wrong
             * about: an unnecessary escalation is a supervisor glancing at a
             * ticket, and the reason is required so the glance is short.
             */
            Capabilities::TICKET_ESCALATE,

            Capabilities::TICKET_CHANGE_STATUS,
            Capabilities::TICKET_CHANGE_DEPARTMENT,
            Capabilities::TICKET_RESOLVE,
            Capabilities::TICKET_REOPEN,
            Capabilities::CUSTOMER_READ,

            /*
             * An agent reads and writes, and does NOT publish.
             *
             * Reading includes the internal articles — those exist precisely
             * so agents can read them. Writing lets an agent draft the answer
             * they have just worked out on a ticket, which is where the good
             * ones come from. Publishing is where it stops: putting an answer
             * in front of every customer is a decision with an audience, and
             * it can never be undone — a published article can only ever be
             * archived, never deleted.
             */
            Capabilities::KNOWLEDGE_VIEW,
            Capabilities::KNOWLEDGE_MANAGE,

            /*
             * An agent answers chat. It is the job.
             *
             * Held by both staff roles rather than gated further, because
             * which people are on chat this afternoon is a rota decision, and
             * a rota expressed as permissions is a rota an administrator has
             * to be found to change.
             */
            Capabilities::CHAT_HANDLE,

            // A label an agent filters their queue by. Reading it is not a
            // privilege; administering the list is.
            Capabilities::BRANCH_READ,
        ],
        Roles::CUSTOMER => [
            // Reading is capped to their OWN tickets by TicketVisibility. The
            // capability says "may read tickets"; which rows is a query
            // question, never a permission-name question.
            Capabilities::TICKET_READ,
            Capabilities::TICKET_CREATE,
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (Capabilities::all() as $capability) {
                Permission::findOrCreate($capability, 'web');
            }

            // Administrator holds every capability implicitly (Gate::before),
            // but the role itself must exist to be assignable.
            Role::findOrCreate(Roles::ADMINISTRATOR, 'web');

            foreach (self::MATRIX as $role => $capabilities) {
                Role::findOrCreate($role, 'web')->syncPermissions($capabilities);
            }
        });

        // Spatie caches the permission map; without this the very next
        // authorization check in the same process reads a stale table.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Exposed so the matrix test asserts against the same source the seeder
     * writes from, rather than a second copy that can drift.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return self::MATRIX;
    }
}
