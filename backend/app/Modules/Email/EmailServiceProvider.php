<?php

declare(strict_types=1);

namespace App\Modules\Email;

use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingType;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
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
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }

    public function registerSettings(SettingsRegistry $registry): void
    {
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
