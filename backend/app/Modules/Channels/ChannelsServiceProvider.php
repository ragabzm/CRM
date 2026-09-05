<?php

declare(strict_types=1);

namespace App\Modules\Channels;

use App\Modules\Channels\Adapters\WebFormChannelAdapter;
use App\Modules\Channels\Console\Commands\ChannelsDoctorCommand;
use App\Modules\Channels\Domain\ChannelAccountAvailability;
use App\Modules\Channels\Domain\Outbound\ChannelSender;
use App\Modules\Channels\Infrastructure\NullChannelTransport;
use App\Modules\Channels\Listeners\SendReplyOnItsChannel;
use App\Modules\Channels\Domain\InboundMessageProvenance;
use App\Modules\Channels\Domain\Intake\CustomerResolver;
use App\Modules\Channels\Domain\Intake\DepartmentResolver;
use App\Modules\Channels\Domain\Intake\InboundIntake;
use App\Modules\Channels\Domain\Intake\TicketCorrelator;
use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Tickets\Contracts\ChannelAvailability;
use App\Modules\Tickets\Contracts\InboundProvenance;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Platform\Support\Settings\SettingType;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Modules\Tickets\Domain\Events\AgentReplyPosted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * The intake spine every transport enters through.
 *
 * One correlator, one idempotency claim, one department rule, one quarantine —
 * bound here as singletons so that "the email path" and "the form path" are the
 * same objects rather than two instances that could be configured apart.
 */
final class ChannelsServiceProvider extends ServiceProvider implements RegistersSettings
{
    public function register(): void
    {
        $this->app->singleton(TicketCorrelator::class);
        $this->app->singleton(CustomerResolver::class);
        $this->app->singleton(DepartmentResolver::class);
        $this->app->singleton(InboundIntake::class);
        $this->app->singleton(WebFormChannelAdapter::class);
        $this->app->singleton(ChannelSender::class);

        /*
         * The transports, TAGGED rather than bound by name.
         *
         * `ChannelSender` picks one by asking each which provider it speaks
         * to, so shipping a real Twilio or Meta adapter is one binding here
         * and no change anywhere else. The null one is always in the list and
         * is what answers when nothing else matches — a fresh checkout and CI
         * both send nothing and record that they sent nothing.
         */
        $this->app->singleton(NullChannelTransport::class);
        $this->app->tag([NullChannelTransport::class], 'channel.transports');

        /*
         * `scoped`, not `singleton`. The instance memoises what it read, and a
         * singleton under a persistent worker would answer every request for
         * the life of the process with the state it saw first.
         */
        $this->app->scoped(ChannelAvailability::class, ChannelAccountAvailability::class);
        $this->app->singleton(InboundProvenance::class, InboundMessageProvenance::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ChannelsDoctorCommand::class]);
        }

        $this->registerRateLimiters();

        /*
         * An agent's reply goes out on the channel the ticket arrived on.
         *
         * Email's own listener has the same guard in reverse, so every ticket
         * has exactly one listener that acts on it — before Story 7.2 the mail
         * dispatcher took every reply, and a WhatsApp answer would have gone
         * out twice.
         */
        Event::listen(AgentReplyPosted::class, SendReplyOnItsChannel::class);
    }

    /**
     * Two limits, because one is not enough.
     *
     * By IP catches a script hammering the form from one place. By contact
     * catches the same address being used from a botnet, where every request
     * has a different IP and the IP limit never fires. Neither is expensive for
     * a person filling in a form once.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('web-form-ip', static fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('web-form-identifier', static fn (Request $request): Limit => Limit::perHour(20)
            ->by('web-form:'.mb_strtolower(trim((string) $request->input('contact', 'anonymous')))));
    }

    public function registerSettings(SettingsRegistry $registry): void
    {
        $registry->register(new SettingDefinition(
            key: DepartmentResolver::SETTING,
            type: SettingType::Int,
            // Zero, not null: the registry types this as a number and zero is
            // the value that means "nobody has chosen one". `channels:doctor`
            // is what refuses a deployment that left it there.
            default: 0,
            summary: 'The department a ticket lands in when nothing else decides. Must be set before go-live.',
        ));

        /*
         * Zero, and that is not an oversight.
         *
         * The window governs the last correlation rule: "this identifier has
         * one open ticket, attach to it". For email that rule was deliberately
         * refused when inbound mail was built — a customer with several open
         * tickets who writes about something new would have it attached to
         * whichever was newest, invisibly to everybody. Turning it on here
         * would change how every existing mailbox behaves as a side effect of
         * a refactor. Email keeps what it has; an administrator who wants the
         * rule can set a window and get it deliberately.
         */
        $registry->register(new SettingDefinition(
            key: 'channels.correlation_window_hours.email',
            type: SettingType::Int,
            default: 0,
            summary: 'How far back an email is matched to an open ticket by sender alone. Zero turns that off.',
        ));

        /*
         * Seventy-two hours, because a form has nothing else.
         *
         * There are no thread headers on a web form and usually no reference:
         * without this rule every follow-up a customer types into the form
         * becomes a new ticket, and one conversation becomes a pile of
         * one-message tickets nobody can follow.
         */
        $registry->register(new SettingDefinition(
            key: 'channels.correlation_window_hours.web_form',
            type: SettingType::Int,
            default: 72,
            summary: 'How far back a form submission is matched to the sender’s open ticket.',
        ));
    }
}
