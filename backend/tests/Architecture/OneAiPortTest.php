<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Ai\Contracts\AiCapability;
use PHPUnit\Framework\TestCase;

/**
 * One governed path to any AI provider, held by the build rather than by
 * review.
 *
 * The rule is easy to state and easy to lose: no feature calls a provider
 * directly. The first time somebody wants a summary and the port does not
 * quite fit, the obvious move is to `use OpenAI\Client` in a Tickets service —
 * and that one line steps around the sanitiser, the capability switch, the
 * transmission mode and the timeout simultaneously. Nothing about the diff
 * looks like it did that.
 *
 * So the SDK names are checked against the whole application, and the
 * sanitiser's unbypassability is checked structurally.
 */
final class OneAiPortTest extends TestCase
{
    /**
     * Provider SDKs and the HTTP hosts that mean the same thing.
     *
     * Matched against CODE only, so a comment explaining why there is no
     * OpenAI client is not itself a violation of the rule it explains.
     *
     * @var list<string>
     */
    private const PROVIDER_SDKS = [
        'OpenAI\\',
        'Anthropic\\',
        'GuzzleHttp\\Client',
        'api.openai.com',
        'api.anthropic.com',
        'generativelanguage.googleapis.com',
        'openai-php',
        'anthropic-sdk',
    ];

    public function test_no_module_outside_ai_reaches_a_provider(): void
    {
        $violations = [];

        foreach (SourceScanner::moduleNames() as $module) {
            if ($module === 'Ai') {
                continue;
            }

            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                $code = SourceScanner::codeOnly($file);

                foreach (self::PROVIDER_SDKS as $sdk) {
                    if (str_contains($code, $sdk)) {
                        $violations[] = basename($file).' reaches a provider directly ('.$sdk.')';
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Everything goes through the one port.\n".implode("\n", $violations),
        );
    }

    public function test_only_the_sanitiser_can_build_what_a_transport_accepts(): void
    {
        $builders = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            $code = SourceScanner::codeOnly($file);

            if (preg_match('/new\s+SanitisedPrompt\s*\(/', $code) === 1) {
                $builders[] = basename($file);
            }
        }

        sort($builders);

        /*
         * The whole guarantee, in one assertion.
         *
         * A transport's signature takes a `SanitisedPrompt`, so raw ticket
         * text cannot reach a provider — unless somebody constructs one
         * somewhere else. PHP cannot make a constructor callable from a single
         * class, so this test is what holds the rule instead.
         */
        $this->assertSame(['Sanitiser.php'], $builders);
    }

    public function test_every_capability_has_its_own_switch(): void
    {
        $provider = (string) file_get_contents(
            SourceScanner::basePath('app/Modules/Ai/AiServiceProvider.php'),
        );

        foreach (AiCapability::cases() as $capability) {
            /*
             * Derived from the enum, so adding a case adds this assertion. A
             * capability that shipped without a switch would be AI running in
             * a deployment that believes it has AI disabled.
             */
            $this->assertStringContainsString(
                $capability->value,
                $provider,
                "[{$capability->value}] has no entry in the settings the console renders.",
            );
        }
    }

    public function test_the_label_is_built_once(): void
    {
        /*
         * No AI artefact reaches a human without saying what it is, and no
         * capability invents its own wording for that. One key, in one file,
         * in both languages.
         */
        foreach (['en', 'ar'] as $locale) {
            $strings = require SourceScanner::basePath("lang/{$locale}/ai.php");

            $this->assertArrayHasKey('label', $strings);
            $this->assertNotSame('', trim((string) $strings['label']));
        }
    }
}
