<?php

declare(strict_types=1);

namespace App\Modules\Tickets;

use App\Modules\Security\Contracts\DepartmentUsageProbe;
use App\Modules\Tickets\Domain\Query\DepartmentTicketUsage;
use Illuminate\Support\ServiceProvider;

/**
 * T3. Owns tickets and the row-level visibility rule.
 */
final class TicketsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Answers Security's DepartmentUsageProbe with a real query, replacing
         * the null implementation Security binds for itself. The dependency
         * runs downward — Tickets (T3) implements Security's (T1) interface —
         * which is what keeps the tier rule intact.
         */
        $this->app->bind(DepartmentUsageProbe::class, DepartmentTicketUsage::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
