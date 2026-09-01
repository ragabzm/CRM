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
            Capabilities::TICKET_READ,
            Capabilities::TICKET_CREATE,
            Capabilities::TICKET_UPDATE,
            Capabilities::TICKET_REASSIGN,
            Capabilities::TICKET_CLOSE,
            Capabilities::CUSTOMER_READ,
            Capabilities::CUSTOMER_MANAGE,
        ],
        Roles::AGENT => [
            Capabilities::TICKET_READ,
            Capabilities::TICKET_CREATE,
            Capabilities::TICKET_UPDATE,
            Capabilities::TICKET_CLOSE,
            Capabilities::CUSTOMER_READ,
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
