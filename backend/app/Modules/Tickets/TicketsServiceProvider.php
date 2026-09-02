<?php

declare(strict_types=1);

namespace App\Modules\Tickets;

use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingType;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Contracts\DepartmentUsageProbe;
use App\Modules\Tickets\Contracts\CategoryUsageProbe;
use App\Modules\Tickets\Domain\CategoryUsage;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Console\Commands\TicketsAutoCloseCommand;
use App\Modules\Tickets\Domain\Commands\AssignTicket;
use App\Modules\Tickets\Domain\Commands\ChangeDepartment;
use App\Modules\Tickets\Domain\Commands\ChangeStatus;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\ReopenTicket;
use App\Modules\Tickets\Domain\Commands\ResolveTicket;
use App\Modules\Tickets\Domain\Commands\UpdateTicketAttributes;
use App\Modules\Tickets\Domain\Concurrency\VersionGuard;
use App\Modules\Tickets\Domain\Events\CustomerReplyPosted;
use App\Modules\Tickets\Domain\Lifecycle\TicketLifecycle;
use App\Modules\Tickets\Http\AssigneeDirectory;
use App\Modules\Tickets\Listeners\ReopenOnCustomerReply;
use App\Modules\Tickets\Domain\Query\DepartmentTicketUsage;
use App\Modules\Tickets\Domain\Reference\PostgresTicketReferenceAllocator;
use App\Modules\Tickets\Domain\Reference\SqliteTicketReferenceAllocator;
use App\Modules\Tickets\Domain\Reference\TicketReferenceAllocator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * T3. Owns tickets and the row-level visibility rule.
 */
final class TicketsServiceProvider extends ServiceProvider implements RegistersSettings
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

        /*
         * How many tickets still use a category. Bound as a singleton so a test
         * — or Story 5.x, when tickets gain a category column — can replace the
         * answer without touching the controller that asks.
         */
        $this->app->singleton(CategoryUsageProbe::class, CategoryUsage::class);

        /*
         * The reference allocator, chosen by driver.
         *
         * Postgres gets an atomic sequence; SQLite — which the test suite runs
         * on — gets a MAX()-based fallback that is only safe because those
         * transactions are serial. Deciding here rather than inside the
         * allocator keeps the unsafe implementation unreachable in production.
         */
        $this->app->singleton(TicketReferenceAllocator::class, function ($app): TicketReferenceAllocator {
            $connection = $app->make(ConnectionInterface::class);

            return $connection->getDriverName() === 'pgsql'
                ? new PostgresTicketReferenceAllocator($connection)
                : new SqliteTicketReferenceAllocator($connection);
        });

        $this->app->singleton(VersionGuard::class);
        $this->app->singleton(TicketLifecycle::class);
        $this->app->singleton(AssigneeDirectory::class);

        // Stateless, so one instance each. Every ticket mutation in the product
        // goes through one of these.
        foreach ([
            AppendMessage::class,
            CreateTicket::class,
            UpdateTicketAttributes::class,
            ChangeDepartment::class,
            AssignTicket::class,
            ChangeStatus::class,
            ResolveTicket::class,
            ReopenTicket::class,
        ] as $command) {
            $this->app->singleton($command);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->commands([TicketsAutoCloseCommand::class]);

        /*
         * A resolved ticket reopens when the customer replies. Wired here so
         * the conversation story only has to fire the event.
         */
        Event::listen(CustomerReplyPosted::class, ReopenOnCustomerReply::class);
    }

    public function registerSettings(SettingsRegistry $registry): void
    {
        $registry->register(new SettingDefinition(
            key: 'tickets.auto_close_window_hours',
            type: SettingType::Int,
            default: 72,
            summary: 'How long a resolved ticket waits, with no word from the customer, before it closes itself.',
            validator: static fn (mixed $v): bool|string => is_int($v) && $v >= 1 && $v <= 24 * 30
                ? true
                // A window under an hour would close tickets before a customer
                // in another timezone had opened their email.
                : 'The auto-close window must be between 1 hour and 30 days.',
        ));

        $registry->register(new SettingDefinition(
            key: 'tickets.reopen_window_days',
            type: SettingType::Int,
            default: 14,
            summary: 'How long after closing a ticket can still be reopened rather than raised again.',
            validator: static fn (mixed $v): bool|string => is_int($v) && $v >= 1 && $v <= 365
                ? true
                : 'The reopen window must be between 1 and 365 days.',
        ));

        $registry->register(new SettingDefinition(
            key: 'tickets.auto_close_hours',
            type: SettingType::Int,
            default: 168,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 1 && $v <= 8760
                ? true
                : 'Must be between 1 hour and a year.',
            summary: 'How long a resolved ticket waits before closing itself.',
        ));

        $registry->register(new SettingDefinition(
            key: 'tickets.reopen_window_hours',
            type: SettingType::Int,
            default: 72,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 0 && $v <= 8760
                ? true
                : 'Must be between 0 hours and a year.',
            summary: 'How long after closing a customer reply reopens the ticket.',
        ));

        $registry->register(new SettingDefinition(
            key: 'tickets.quick_replies',
            type: SettingType::Json,
            default: [],
            validator: static function (mixed $value): true|string {
                if (! is_array($value)) {
                    return 'Must be a list of quick replies.';
                }

                $seen = [];

                foreach ($value as $reply) {
                    if (! is_array($reply)) {
                        return 'Each quick reply must be an object.';
                    }

                    foreach (['id', 'label', 'body'] as $field) {
                        if (! array_key_exists($field, $reply)) {
                            return "Each quick reply needs {$field}.";
                        }
                    }

                    if (! is_string($reply['id']) || $reply['id'] === '') {
                        return 'Each quick reply needs an id.';
                    }

                    if (in_array($reply['id'], $seen, true)) {
                        return 'Quick reply ids must be unique.';
                    }

                    $seen[] = $reply['id'];

                    // Both languages, always. A reply that exists in one
                    // language is a gap an agent discovers mid-conversation.
                    foreach (['label', 'body'] as $field) {
                        $translated = $reply[$field];

                        if (! is_array($translated)) {
                            return "Each quick reply {$field} needs en and ar.";
                        }

                        foreach (['en', 'ar'] as $locale) {
                            if (! isset($translated[$locale]) || ! is_string($translated[$locale]) || trim($translated[$locale]) === '') {
                                return "Each quick reply {$field} needs a non-empty {$locale} value.";
                            }
                        }
                    }
                }

                return true;
            },
            summary: 'Shared saved replies. Plain text — no variables or templates.',
        ));
    }
}
