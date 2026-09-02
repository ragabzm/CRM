<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Customers\Domain\CustomerSearch;
use App\Modules\Customers\Domain\DuplicateDetector;
use Illuminate\Support\ServiceProvider;

/**
 * Registration seam for the Customers module.
 *
 * Routes live in the central routes/api.php with every other module's, so the
 * whole HTTP surface is readable in one file — see the comment there.
 */
final class CustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CustomerSearch::class);
        $this->app->singleton(DuplicateDetector::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
