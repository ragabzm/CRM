<?php

declare(strict_types=1);

namespace App\Modules\Email;

use App\Modules\Email\Console\Commands\PruneMailLogCommand;
use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Domain\MailLog;
use App\Modules\Email\Domain\OutboundMailer;
use App\Modules\Email\Domain\SubjectTagger;
use App\Modules\Email\Infrastructure\LaravelMailTransport;
use App\Modules\Email\Domain\Inbound\InboundMailIntake;
use App\Modules\Email\Domain\Inbound\MailParser;
use App\Modules\Email\Domain\Inbound\SenderResolver;
use App\Modules\Email\Domain\Inbound\TicketCorrelator;
use App\Modules\Email\Domain\OutboundDispatcher;
use App\Modules\Email\Infrastructure\NullMailTransport;
use App\Modules\Email\Listeners\SendAcknowledgement;
use App\Modules\Email\Listeners\SendAgentReply;
use App\Modules\Tickets\Domain\Events\AgentReplyPosted;
use App\Modules\Tickets\Domain\Events\TicketOpened;
use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingType;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * T4. Owns the inbound mailbox and outbound acknowledgements.
 *
 * Story 1.4 registers the SETTINGS only; polling the mailbox and sending the
 * acknowledgement is Story 5.1.
 */
final class EmailServiceProvider extends ServiceProvider implements RegistersSettings
{
    public function register(): void
    {
        /*
         * The provider is a SETTING, not a dependency.
         *
         * Everything that sends mail depends on the MailTransport port;
         * swapping SMTP for Postmark changes a stored value and nothing else.
         * The default is the null transport, so a fresh checkout, CI, and a
         * laptop with no SMTP server all work — a test suite that quietly
         * required a reachable provider is a suite nobody can run.
         */
        $this->app->singleton(NullMailTransport::class);

        $this->app->singleton(MailTransport::class, function ($app): MailTransport {
            $settings = $app->make(SettingsRegistry::class);
            $provider = (string) $settings->get('email.provider');

            if ($provider === 'null') {
                return $app->make(NullMailTransport::class);
            }

            return new LaravelMailTransport(
                $app->make(MailFactory::class),
                $provider,
                (string) $settings->get('email.from_address'),
                (string) $settings->get('email.from_name'),
            );
        });

        $this->app->singleton(MailLog::class);
        $this->app->singleton(OutboundMailer::class);
        $this->app->singleton(SubjectTagger::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneMailLogCommand::class]);
        }

        $this->app->singleton(OutboundDispatcher::class);
        $this->app->singleton(MailParser::class);
        $this->app->singleton(TicketCorrelator::class);
        $this->app->singleton(SenderResolver::class);
        $this->app->singleton(InboundMailIntake::class);

        /*
         * Email listens to Tickets, never the other way round. T4 depending on
         * T3 runs downward and is legal; a call from CreateTicket into this
         * module would not be.
         */
        Event::listen(TicketOpened::class, SendAcknowledgement::class);
        Event::listen(AgentReplyPosted::class, SendAgentReply::class);
    }

    public function registerSettings(SettingsRegistry $registry): void
    {
        $registry->register(new SettingDefinition(
            key: 'email.enabled',
            type: SettingType::Bool,
            default: false,
            // Off until somebody configures it. A channel that starts on would
            // try to email real customers the moment the application boots.
            summary: 'Whether the application sends and receives email at all.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.provider',
            type: SettingType::Enum,
            default: 'null',
            allowedValues: ['null', 'smtp', 'postmark', 'mailgun', 'ses', 'log'],
            summary: 'Which service delivers outbound mail. Changing it needs no code change.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.from_address',
            type: SettingType::String,
            default: '',
            validator: static fn (mixed $v): true|string => $v === '' || (is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL) !== false)
                ? true
                : 'Must be an email address.',
            summary: 'The address customers see replies come from.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.from_name',
            type: SettingType::String,
            default: 'Support',
            summary: 'The name customers see replies come from.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.domain',
            type: SettingType::String,
            default: 'localhost',
            /*
             * Used to build Message-IDs. A wrong value does not break sending,
             * but it breaks Story 5.2's ability to recognise a reply to one of
             * our own messages — so it is worth setting deliberately.
             */
            summary: 'Domain used in the Message-ID of outgoing mail.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.provider_credential',
            type: SettingType::String,
            default: '',
            // Write-only. Never echoed back, not even to the administrator who
            // set it: a value that can be read back leaks through a screen share.
            secret: true,
            summary: 'API key or password for the mail provider.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.acknowledgement.enabled',
            type: SettingType::Bool,
            default: true,
            summary: 'Send an automatic acknowledgement when a ticket is created.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.log.retention_days',
            type: SettingType::Int,
            default: 90,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 1 && $v <= 3650
                ? true
                : 'Must be between 1 and 3650 days.',
            summary: 'How long mail log entries are kept before being pruned.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.inbound.enabled',
            type: SettingType::Bool,
            default: false,
            // Off until somebody configures it. An open, unauthenticated write
            // endpoint is not something to have by default.
            summary: 'Whether the inbound webhook accepts mail.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.inbound.provider',
            type: SettingType::String,
            default: 'generic',
            summary: 'Which service delivers inbound mail to the webhook.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.inbound.webhook_secret',
            type: SettingType::String,
            default: '',
            /*
             * The only thing standing between this endpoint and anybody who
             * finds the URL. Write-only: a secret that can be read back leaks
             * through a screen share, and this one lets its holder open tickets
             * in any customer's name.
             */
            secret: true,
            summary: 'Shared secret the provider sends with each delivery.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.mailbox.host',
            type: SettingType::String,
            default: '',
            summary: 'Hostname of the mailbox tickets arrive in.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.mailbox.port',
            type: SettingType::Int,
            default: 993,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 1 && $v <= 65535
                ? true
                : 'Must be a port between 1 and 65535.',
            summary: 'Port for the mailbox connection.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.mailbox.username',
            type: SettingType::String,
            default: '',
            summary: 'Account used to read the mailbox.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.mailbox.password',
            type: SettingType::String,
            default: '',
            // Never echoed back, not even to the administrator who set it: a
            // value that can be read back leaks through a screen share.
            secret: true,
            summary: 'Password for the mailbox account.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.mailbox.encryption',
            type: SettingType::Enum,
            default: 'ssl',
            allowedValues: ['ssl', 'tls', 'none'],
            summary: 'Transport encryption for the mailbox connection.',
        ));

        $registry->register(new SettingDefinition(
            key: 'email.acknowledgement_template',
            type: SettingType::Json,
            default: [
                'en' => 'Thank you for contacting us. Your ticket has been logged and an agent will reply shortly.',
                'ar' => 'شكرًا لتواصلك معنا. تم تسجيل تذكرتك وسيرد عليك أحد الوكلاء قريبًا.',
            ],
            validator: static function (mixed $value): true|string {
                if (! is_array($value)) {
                    return 'Must provide the template in both languages.';
                }

                foreach (['en', 'ar'] as $locale) {
                    if (! isset($value[$locale]) || ! is_string($value[$locale]) || trim($value[$locale]) === '') {
                        return "The {$locale} acknowledgement cannot be empty — a customer writing in that language would receive nothing.";
                    }
                }

                return true;
            },
            summary: 'Automatic reply sent when a ticket is created from email.',
        ));
    }
}
