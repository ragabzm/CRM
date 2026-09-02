<?php

declare(strict_types=1);

namespace App\Modules\Sla;

use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingType;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Tickets\Domain\Priority;
use Illuminate\Support\ServiceProvider;

/**
 * T4. Owns service-level targets and the clock they are measured against.
 *
 * Story 1.4 registers the SETTINGS only; the behaviour that consumes them —
 * pausing the clock, escalating, marking at risk — is Story 5.3.
 */
final class SlaServiceProvider extends ServiceProvider implements RegistersSettings
{
    /** Response targets default to an hour for urgent, scaling out from there. */
    private const RESPONSE_DEFAULTS = [
        'low' => 172800,      // 2 days
        'normal' => 86400,    // 1 day
        'high' => 14400,      // 4 hours
        'urgent' => 3600,     // 1 hour
    ];

    private const RESOLUTION_DEFAULTS = [
        'low' => 1209600,     // 14 days
        'normal' => 432000,   // 5 days
        'high' => 86400,      // 1 day
        'urgent' => 14400,    // 4 hours
    ];

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
        /*
         * One target per priority, keyed by the enum's own values rather than a
         * hand-written list — a fifth priority could then never be added
         * without its targets appearing here too.
         */
        foreach (Priority::values() as $priority) {
            $registry->register(new SettingDefinition(
                key: "sla.response_target_seconds.{$priority}",
                type: SettingType::DurationSeconds,
                default: self::RESPONSE_DEFAULTS[$priority],
                validator: self::positiveDuration(),
                summary: "First response target for {$priority} priority.",
            ));

            $registry->register(new SettingDefinition(
                key: "sla.resolution_target_seconds.{$priority}",
                type: SettingType::DurationSeconds,
                default: self::RESOLUTION_DEFAULTS[$priority],
                validator: self::positiveDuration(),
                summary: "Resolution target for {$priority} priority.",
            ));
        }

        $registry->register(new SettingDefinition(
            key: 'sla.working_hours',
            type: SettingType::Json,
            default: [
                'sun' => ['09:00', '17:00'],
                'mon' => ['09:00', '17:00'],
                'tue' => ['09:00', '17:00'],
                'wed' => ['09:00', '17:00'],
                'thu' => ['09:00', '17:00'],
                'fri' => null,
                'sat' => null,
            ],
            validator: static function (mixed $value): true|string {
                if (! is_array($value)) {
                    return 'Must be a map of weekdays.';
                }

                foreach (['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as $day) {
                    if (! array_key_exists($day, $value)) {
                        return "Missing {$day}. Every weekday must be present, using null for a closed day.";
                    }

                    $hours = $value[$day];

                    // null is a closed day, and is different from an empty
                    // range: absent would be ambiguous, null is a decision.
                    if ($hours === null) {
                        continue;
                    }

                    if (! is_array($hours) || count($hours) !== 2) {
                        return "{$day} must be null or a [start, end] pair.";
                    }

                    foreach ($hours as $time) {
                        if (! is_string($time) || preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $time) !== 1) {
                            return "{$day} times must be HH:MM in 24-hour form.";
                        }
                    }

                    if ($hours[0] >= $hours[1]) {
                        return "{$day} must start before it ends.";
                    }
                }

                return true;
            },
            summary: 'The week the SLA clock runs against. null closes a day.',
        ));

        $registry->register(new SettingDefinition(
            key: 'sla.holidays',
            type: SettingType::Json,
            default: [],
            validator: static function (mixed $value): true|string {
                if (! is_array($value)) {
                    return 'Must be a list of dates.';
                }

                foreach ($value as $date) {
                    if (! is_string($date) || preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) !== 1) {
                        return 'Each holiday must be a date in YYYY-MM-DD form.';
                    }
                }

                return true;
            },
            summary: 'Dates on which the SLA clock does not run.',
        ));

        $registry->register(new SettingDefinition(
            key: 'sla.at_risk_threshold_percent',
            type: SettingType::Int,
            default: 80,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 1 && $v <= 99
                ? true
                : 'Must be between 1 and 99 percent. At 100 a ticket would only be at risk once it had already breached.',
            summary: 'How much of the target must elapse before a ticket is at risk.',
        ));
    }

    /** @return \Closure(mixed): (true|string) */
    private static function positiveDuration(): \Closure
    {
        return static fn (mixed $v): true|string => is_int($v) && $v >= 60 && $v <= 7776000
            ? true
            : 'Must be between a minute and 90 days, in seconds.';
    }
}
