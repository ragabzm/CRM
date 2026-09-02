<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Settings;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Platform\Support\Settings\SettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class SettingsRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function registry(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    public function test_an_unregistered_key_cannot_be_read_or_written(): void
    {
        // There is no way to write an arbitrary key. That is the difference
        // between a settings table and a key-value dumping ground.
        $this->expectException(ProblemException::class);

        $this->registry()->get('nothing.declared_this');
    }

    public function test_a_key_cannot_be_registered_twice(): void
    {
        $registry = $this->registry();

        $this->expectException(InvalidArgumentException::class);

        // Two modules claiming one key would make "who owns this?"
        // unanswerable.
        $registry->register(new SettingDefinition(
            key: 'platform.default_locale',
            type: SettingType::String,
            default: 'en',
        ));
    }

    public function test_an_enum_must_declare_its_allowed_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry()->register(new SettingDefinition(
            key: 'test.enum_without_values',
            type: SettingType::Enum,
            default: 'a',
        ));
    }

    public function test_the_default_is_returned_when_no_row_exists(): void
    {
        $this->assertSame(168, $this->registry()->get('tickets.auto_close_hours'));
    }

    public function test_a_write_is_visible_to_the_very_next_read(): void
    {
        $registry = $this->registry();

        $registry->set('tickets.auto_close_hours', 24, null);

        // "Takes effect immediately" means THIS request, not the next one — the
        // cache and the per-request memo are both busted synchronously.
        $this->assertSame(24, $registry->get('tickets.auto_close_hours'));
    }

    public function test_a_write_survives_a_fresh_registry(): void
    {
        $this->registry()->set('tickets.auto_close_hours', 24, null);

        $this->app->forgetInstance(SettingsRegistry::class);

        $this->assertSame(24, $this->app->make(SettingsRegistry::class)->get('tickets.auto_close_hours'));
    }

    public function test_set_reports_what_changed(): void
    {
        $result = $this->registry()->set('tickets.auto_close_hours', 24, null);

        // before AND after: an audit entry recording only the new value cannot
        // answer "what did this used to be?".
        $this->assertSame(['before' => 168, 'after' => 24], $result);
    }

    public function test_an_invalid_value_is_refused_with_the_reason(): void
    {
        try {
            $this->registry()->set('tickets.auto_close_hours', 0, null);
            $this->fail('Expected the write to be refused.');
        } catch (ProblemException $e) {
            $this->assertSame(422, $e->problem->status);
            // The message names the bound rather than saying "invalid".
            $this->assertStringContainsString('between 1 hour', (string) $e->problem->detail);
        }
    }

    public function test_a_value_of_the_wrong_type_is_refused(): void
    {
        $this->expectException(ProblemException::class);

        $this->registry()->set('tickets.auto_close_hours', 'twenty-four', null);
    }

    public function test_an_enum_refuses_a_value_outside_its_list(): void
    {
        $this->expectException(ProblemException::class);

        $this->registry()->set('platform.default_locale', 'fr', null);
    }

    public function test_a_refused_write_leaves_the_previous_value_intact(): void
    {
        $registry = $this->registry();
        $registry->set('tickets.auto_close_hours', 24, null);

        try {
            $registry->set('tickets.auto_close_hours', -5, null);
        } catch (ProblemException) {
            // expected
        }

        $this->assertSame(24, $registry->get('tickets.auto_close_hours'));
    }

    public function test_a_stored_value_that_no_longer_passes_falls_back_to_the_default(): void
    {
        $registry = $this->registry();
        $registry->set('sla.at_risk_threshold_percent', 90, null);

        // Simulates a definition tightening after a value was written: the
        // stored value is no longer allowed, so the default is returned rather
        // than something the current rules forbid.
        \Illuminate\Support\Facades\DB::table('settings')
            ->where('key', 'sla.at_risk_threshold_percent')
            ->update(['value' => json_encode(150)]);
        $registry->flush();

        $this->assertSame(80, $registry->get('sla.at_risk_threshold_percent'));
    }

    public function test_secrets_are_redacted_everywhere_they_could_be_read(): void
    {
        $registry = $this->registry();
        $registry->set('email.mailbox.password', 'hunter2-and-then-some', null);

        $all = $registry->all();

        // Never echoed back, not even to the administrator who set it.
        $this->assertNotSame('hunter2-and-then-some', $all['email.mailbox.password']);
        $this->assertSame('••••••••', $all['email.mailbox.password']);

        // ...but the real value is still readable by the code that needs it.
        $this->assertSame('hunter2-and-then-some', $registry->get('email.mailbox.password'));
    }

    public function test_an_unset_secret_redacts_to_null_not_to_dots(): void
    {
        // Otherwise the console would show "••••••••" for a password nobody has
        // set, and an administrator would think the mailbox was configured.
        $this->assertNull($this->registry()->all()['email.mailbox.password']);
    }

    public function test_every_registered_key_resolves(): void
    {
        $registry = $this->registry();

        foreach ($registry->knownKeys() as $key) {
            $definition = $registry->definition($key);

            // A default that fails its own validator would make the setting
            // unreadable until someone wrote a value.
            $this->assertTrue(
                $definition->validate($definition->default) === true,
                "Default for [{$key}] does not satisfy its own rule.",
            );
        }
    }

    public function test_the_registered_key_set_is_the_documented_one(): void
    {
        /*
         * Pinned deliberately. These keys are strings in the console's TSX and
         * in whatever module reads them at runtime, so a rename here is silent
         * in both places: the console renders an empty panel and the reader
         * assumes the setting is simply unset. Changing this list is fine —
         * changing it WITHOUT noticing is the failure mode.
         */
        $this->assertSame([
            'email.acknowledgement_template',
            'email.mailbox.encryption',
            'email.mailbox.host',
            'email.mailbox.password',
            'email.mailbox.port',
            'email.mailbox.username',
            'platform.attachments.allowed_mime_types',
            'platform.attachments.max_bytes',
            'platform.default_locale',
            'sla.at_risk_threshold_percent',
            'sla.holidays',
            'sla.resolution_target_seconds.high',
            'sla.resolution_target_seconds.low',
            'sla.resolution_target_seconds.normal',
            'sla.resolution_target_seconds.urgent',
            'sla.response_target_seconds.high',
            'sla.response_target_seconds.low',
            'sla.response_target_seconds.normal',
            'sla.response_target_seconds.urgent',
            'sla.working_hours',
            'tickets.auto_close_hours',
            'tickets.quick_replies',
            'tickets.reopen_window_hours',
        ], $this->registry()->knownKeys());
    }

    public function test_every_priority_has_both_targets(): void
    {
        $keys = $this->registry()->knownKeys();

        // A priority without a target is a ticket nothing is ever measured
        // against — it would simply never appear on an SLA report.
        foreach (\App\Modules\Tickets\Domain\Priority::values() as $priority) {
            $this->assertContains("sla.response_target_seconds.{$priority}", $keys);
            $this->assertContains("sla.resolution_target_seconds.{$priority}", $keys);
        }
    }

    public function test_the_registry_reports_its_known_keys_sorted(): void
    {
        $keys = $this->registry()->knownKeys();
        $sorted = $keys;
        sort($sorted);

        $this->assertSame($sorted, $keys);
        $this->assertContains('tickets.quick_replies', $keys);
    }
}
