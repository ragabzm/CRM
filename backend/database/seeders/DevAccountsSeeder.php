<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

/**
 * The three accounts a developer signs in with, one per staff role.
 *
 * They existed before this file did — created by hand through tinker — which
 * meant `migrate:fresh --seed` silently destroyed them and left nobody able to
 * reach the Administration console. That happened: the users table was found
 * empty while `.squad/DEV-CREDENTIALS.md` still documented the three accounts
 * as present. Accounts a team signs in with every day belong in the seeder, not
 * in someone's shell history.
 *
 * One per role on purpose. Most authorization bugs only appear when you are NOT
 * an administrator, and a developer who only ever holds every capability never
 * meets them.
 */
final class DevAccountsSeeder extends Seeder
{
    /**
     * Fixed and published in `.squad/DEV-CREDENTIALS.md`.
     *
     * A shared, known password is correct here and nowhere else: this seeder
     * refuses to run outside local development, so there is no environment in
     * which knowing it grants anything.
     */
    public const PASSWORD = 'Correct-Horse-9';

    private const ACCOUNTS = [
        ['Admin', 'admin@ragab.test', Roles::ADMINISTRATOR],
        ['Super', 'super@ragab.test', Roles::SUPERVISOR],
        ['Agent', 'agent@ragab.test', Roles::AGENT],
    ];

    public function run(): void
    {
        /*
         * The guard is the whole reason a known password is acceptable above.
         * `production` is not the only environment that matters — staging holds
         * real people's data too — so this allows local development only, by
         * name, rather than excluding the one name we remembered.
         */
        if (! App::environment(['local', 'testing'])) {
            $this->command?->warn('DevAccountsSeeder skipped: local development only.');

            return;
        }

        /*
         * Idempotent, and required: running this seeder on its own —
         * `db:seed --class=DevAccountsSeeder`, which is exactly how someone
         * recovers after a fresh — would otherwise fail with "there is no role
         * named administrator". DatabaseSeeder calls it first too; findOrCreate
         * makes the second call free.
         */
        $this->call(RolesAndPermissionsSeeder::class);

        foreach (self::ACCOUNTS as [$name, $email, $role]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            // syncRoles, not assignRole: re-seeding must not leave an account
            // holding a role it was moved out of.
            $user->syncRoles([$role]);
        }

        $this->command?->info('Seeded 3 development accounts (see .squad/DEV-CREDENTIALS.md).');
    }
}
