<?php

declare(strict_types=1);

namespace App\Modules\Sla;

use Illuminate\Support\ServiceProvider;

/**
 * Registration seam for the Sla module.
 *
 * Story 1.1 ships the module as an empty scaffold: the provider exists so the
 * module owns its own migrations and bindings from day one, and so later
 * stories have a place to put them without touching shared bootstrap files.
 */
final class SlaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
