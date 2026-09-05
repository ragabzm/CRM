<?php

declare(strict_types=1);

namespace App\Modules\Knowledge;

use App\Modules\Knowledge\Domain\HtmlSanitiser;
use App\Modules\Knowledge\Domain\Lifecycle\ArticleLifecycle;
use Illuminate\Support\ServiceProvider;

/**
 * Articles, their categories and their lifecycle.
 *
 * Depends on Platform (attachments, audit) and Security (capabilities), and on
 * nothing else. An article about refunds and a ticket about refunds are related
 * by meaning, not by a foreign key — see `module-tiers.php`.
 */
final class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * A singleton because building the sanitiser compiles its allow-list,
         * and an article list saving twenty bodies would otherwise build it
         * twenty times.
         */
        $this->app->singleton(HtmlSanitiser::class);
        $this->app->singleton(ArticleLifecycle::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
