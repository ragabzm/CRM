<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Before anything that assigns a role.
        $this->call(RolesAndPermissionsSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        /*
         * The accounts the team actually signs in with. They used to be created
         * by hand, so `migrate:fresh --seed` destroyed them and left the
         * Administration console unreachable. The seeder itself refuses to run
         * outside local development.
         */
        $this->call(DevAccountsSeeder::class);
    }
}
