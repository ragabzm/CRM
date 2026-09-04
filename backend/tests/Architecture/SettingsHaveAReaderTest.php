<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Tests\TestCase;

/**
 * Every setting the console offers is one that something reads.
 *
 * The registry declares a setting; the Administration console renders whatever
 * the registry declares. So a key nobody consumes is not dead code sitting
 * quietly out of sight — it is a control an administrator can find, change,
 * and be told was saved, which changes nothing.
 *
 * That happened. `tickets.auto_close_hours` and `tickets.reopen_window_hours`
 * were registered alongside `tickets.auto_close_window_hours` and
 * `tickets.reopen_window_days` — the same two policies declared twice, in
 * different units. `TicketLifecycle` reads the second pair. The console showed
 * the first, and an administrator adjusting the reopen window there was
 * adjusting a number the product never looks at.
 *
 * Nothing caught it because both pairs were valid settings: they registered,
 * validated, saved and read back correctly. Every test about them passed. The
 * only thing wrong was that no part of the product asked for the answer.
 */
final class SettingsHaveAReaderTest extends TestCase
{
    /**
     * Keys with no PHP reader, each for a stated reason.
     *
     * Every entry here is a control an administrator can change that changes
     * nothing in the backend, so the list is deliberately uncomfortable to
     * add to. It is not a suppression list for orphans — it is a list of
     * settings whose consumer is somewhere this scan cannot see, or is
     * honestly not built yet.
     *
     * @var array<string, string> key => why
     */
    private const NO_PHP_READER = [
        // Sent to the client and rendered there. The backend stores it and
        // never asks what it says.
        'tickets.quick_replies' => 'consumed by the frontend composer, not by PHP',

        /*
         * Declared ahead of their consumer. Inbound mail arrives by webhook
         * (`email.inbound.*`); polling a mailbox was specified and never
         * built, so these five are an empty promise on the Email screen.
         * Either build the poller or take them off the console.
         */
        'email.mailbox.host' => 'mailbox polling is not implemented',
        'email.mailbox.port' => 'mailbox polling is not implemented',
        'email.mailbox.username' => 'mailbox polling is not implemented',
        'email.mailbox.password' => 'mailbox polling is not implemented',
        'email.mailbox.encryption' => 'mailbox polling is not implemented',

        // The interface picks its own language from the request; nothing on
        // the server consults this.
        'platform.default_locale' => 'the frontend decides the locale',

        /*
         * The worst entry on this list, and the reason the list names a reason
         * for each one. `LaravelMailTransport` is built from `email.provider`,
         * `from_address` and `from_name`; the credential is not passed to it,
         * and the mailer's real credentials come from `config/mail.php`. So an
         * administrator pastes a live API key into a field that stores it and
         * uses it for nothing.
         *
         * Either wire it into the transport or take the field off the screen.
         * Storing a secret nobody reads is all of the risk and none of the use.
         */
        'email.provider_credential' => 'NOT WIRED — the transport never reads it; see .squad/gaps',
    ];

    public function test_every_registered_setting_is_read_somewhere(): void
    {
        $sources = $this->phpSources();

        $orphans = [];

        foreach ($this->registeredKeys() as $key) {
            if (array_key_exists($key, self::NO_PHP_READER)) {
                continue;
            }

            if (! $this->isMentionedOutsideItsRegistration($key, $sources)) {
                $orphans[] = $key;
            }
        }

        $this->assertSame(
            [],
            $orphans,
            "These settings are offered in the console and read by nothing:\n  ".implode("\n  ", $orphans),
        );
    }

    public function test_the_scan_finds_the_settings_at_all(): void
    {
        /*
         * Guarding the guard. A registry that boots empty, or a scan that
         * matches nothing, would report a clean result while checking nothing.
         */
        $keys = $this->registeredKeys();

        $this->assertGreaterThan(20, count($keys));
        $this->assertContains('tickets.reopen_window_days', $keys);
    }

    public function test_it_would_catch_a_setting_with_no_reader(): void
    {
        // The negative case, run against the same matcher the test above uses.
        $this->assertFalse(
            $this->isMentionedOutsideItsRegistration('tickets.invented_for_this_test', $this->phpSources()),
        );
    }

    /**
     * @return list<string>
     */
    private function registeredKeys(): array
    {
        /*
         * `definitions()`, not `all()`: this asks what the code DECLARES, and
         * `all()` would go to the database for stored overrides — a question
         * about deployment state, not about the source.
         */
        return array_values(array_map(
            static fn (object $definition): string => $definition->key,
            $this->app->make(SettingsRegistry::class)->definitions(),
        ));
    }

    /**
     * @param  array<string, string>  $sources  path => contents
     */
    private function isMentionedOutsideItsRegistration(string $key, array $sources): bool
    {
        foreach ($sources as $path => $contents) {
            /*
             * A key's own registration mentions it by definition, so
             * `registerSettings()` is cut out before the scan — rather than
             * skipping providers wholesale, which would miss the ones that
             * both declare a setting and read it (`EmailServiceProvider`
             * builds the transport from `email.from_address`).
             */
            $readable = self::withoutRegistrations($contents);

            if (str_contains($readable, $key)) {
                return true;
            }

            /*
             * Whole keys only, deliberately. Matching a prefix would let an
             * interpolated `"sla.{$timer}_target_seconds.{$priority}"` count
             * as a reader for keys nobody had checked — and interpolated keys
             * are precisely what let a dead pair of settings survive. `SlaClock`
             * now writes every one of its keys out for the same reason.
             */
        }

        return false;
    }

    /**
     * The file with every `registerSettings()` body removed.
     *
     * Crude on purpose: it cuts from the method signature to the end of the
     * file. Registration is the last thing in every provider that has one, and
     * a brace-matching parser here would be a second thing to get wrong.
     */
    private static function withoutRegistrations(string $contents): string
    {
        $at = strpos($contents, 'function registerSettings');

        return $at === false ? $contents : substr($contents, 0, $at);
    }

    /**
     * @return array<string, string>
     */
    private function phpSources(): array
    {
        $sources = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $sources;
    }
}
