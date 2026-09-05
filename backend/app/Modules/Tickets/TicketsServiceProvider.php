<?php

declare(strict_types=1);

namespace App\Modules\Tickets;

use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Tickets\Domain\Commands\RateTicket;
use App\Modules\Platform\Support\Settings\SettingType;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Contracts\DepartmentUsageProbe;
use App\Modules\Tickets\Application\Portal\CustomerRequests;
use App\Modules\Tickets\Console\Commands\RemindersSweepCommand;
use App\Modules\Tickets\Console\Commands\TicketsAutoCloseCommand;
use App\Modules\Tickets\Contracts\CategoryUsageProbe;
use App\Modules\Tickets\Contracts\ChannelAvailability;
use App\Modules\Tickets\Contracts\CustomerRequestGateway;
use App\Modules\Tickets\Contracts\InboundProvenance;
use App\Modules\Tickets\Contracts\TicketEventRecording;
use App\Modules\Tickets\Domain\CategoryUsage;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Commands\AssignTicket;
use App\Modules\Tickets\Domain\Commands\ChangeDepartment;
use App\Modules\Tickets\Domain\Commands\ChangeStatus;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\ReopenTicket;
use App\Modules\Tickets\Domain\Commands\ResolveTicket;
use App\Modules\Tickets\Domain\Commands\UpdateTicketAttributes;
use App\Modules\Tickets\Domain\Concurrency\VersionGuard;
use App\Modules\Tickets\Domain\Events\CustomerReplyPosted;
use App\Modules\Tickets\Domain\Events\TicketAssigned;
use App\Modules\Tickets\Domain\EverythingIsOpen;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\Lifecycle\TicketLifecycle;
use App\Modules\Tickets\Domain\NoInboundProvenance;
use App\Modules\Tickets\Domain\Query\DepartmentTicketUsage;
use App\Modules\Tickets\Domain\Query\TicketCounts;
use App\Modules\Tickets\Domain\Query\TicketListQuery;
use App\Modules\Tickets\Domain\Reference\PostgresTicketReferenceAllocator;
use App\Modules\Tickets\Domain\Reference\SqliteTicketReferenceAllocator;
use App\Modules\Tickets\Domain\Reference\TicketReferenceAllocator;
use App\Modules\Tickets\Http\AssigneeDirectory;
use App\Modules\Tickets\Listeners\NotifyOnCustomerReply;
use App\Modules\Tickets\Listeners\NotifyOnTicketAssigned;
use App\Modules\Tickets\Listeners\ReopenOnCustomerReply;
use App\Modules\Tickets\Notifications\TicketNotifier;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Console\Scheduling\Schedule;
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
         * The safe default, overridden by Channels when that module is
         * present. Bound here so Tickets works on its own — in a unit test, or
         * in a deployment that has not enabled Channels.
         */
        $this->app->bind(ChannelAvailability::class, EverythingIsOpen::class);
        $this->app->bind(InboundProvenance::class, NoInboundProvenance::class);

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

        /*
         * The one writer of ticket history. A singleton so that every command
         * in a request shares it, and bound to the contract so the SLA module
         * can record a breach without depending on the Tickets model.
         */
        $this->app->singleton(TicketEventRecorder::class);
        $this->app->singleton(TicketNotifier::class);

        /*
         * The narrow surface the portal reads through. Bound here, in Tickets,
         * so the portal depends on a contract rather than on this module's
         * commands, models and rules.
         */
        $this->app->singleton(CustomerRequestGateway::class, CustomerRequests::class);

        // Stateless: they hold nothing per request, so one instance serves the
        // whole application.
        $this->app->singleton(TicketListQuery::class);
        $this->app->singleton(TicketCounts::class);
        $this->app->bind(TicketEventRecording::class, TicketEventRecorder::class);

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

        $this->commands([TicketsAutoCloseCommand::class, RemindersSweepCommand::class]);

        /*
         * Minutely, and on one server.
         *
         * A reminder set for 09:00 that arrives at 09:15 is a reminder somebody
         * stops trusting, so the sweep's interval IS the resolution of the
         * whole feature. `onOneServer` because two schedulers running the same
         * minute would each pick up the same due rows — the sweep survives
         * that by construction, and not relying on it is cheaper than proving
         * it every deployment.
         */
        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command(RemindersSweepCommand::class)
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->onOneServer();
            });
        }

        /*
         * A resolved ticket reopens when the customer replies. Wired here so
         * the conversation story only has to fire the event.
         */
        Event::listen(CustomerReplyPosted::class, ReopenOnCustomerReply::class);

        /*
         * Three notification triggers, and no others.
         *
         * Listeners rather than calls inside the commands: a trigger can be
         * switched off by removing one line, and a command stays about the
         * change it makes rather than about who hears of it.
         */
        Event::listen(TicketAssigned::class, NotifyOnTicketAssigned::class);
        Event::listen(CustomerReplyPosted::class, NotifyOnCustomerReply::class);
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
            key: RateTicket::WINDOW_SETTING,
            type: SettingType::Int,
            /*
             * Twenty-four hours. Long enough that somebody who tapped the
             * wrong one and noticed the next morning can fix it; short enough
             * that a figure a manager is looking at stops moving under them.
             *
             * Zero is allowed and locks a rating the moment it is given.
             */
            default: 24,
            summary: 'How long a customer can change their answer about how a request went.',
            validator: static fn (mixed $v): bool|string => is_int($v) && $v >= 0 && $v <= 24 * 30
                ? true
                : 'The rating change window must be between 0 hours and 30 days.',
        ));

        /*
         * There used to be two more here — `tickets.auto_close_hours` and
         * `tickets.reopen_window_hours` — declaring the same two policies a
         * second time in a different unit. Nothing read either of them:
         * `TicketLifecycle` names `auto_close_window_hours` and
         * `reopen_window_days`.
         *
         * They were not harmless. The Administration console rendered the
         * settings the registry declares, so it showed the DEAD pair, and an
         * administrator who changed the reopen window there changed nothing at
         * all — with a success message. `SettingsHaveAReaderTest` now fails on
         * any setting nothing reads.
         */

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
