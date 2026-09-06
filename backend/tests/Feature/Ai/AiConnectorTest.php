<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Modules\Ai\Contracts\AiCapability;
use App\Modules\Ai\Contracts\AiProvider;
use App\Modules\Ai\Contracts\AiTransport;
use App\Modules\Ai\Domain\GuardedAiProvider;
use App\Modules\Ai\Domain\Sanitiser\Sanitiser;
use App\Modules\Ai\Domain\TransmissionMode;
use App\Modules\Ai\Infrastructure\NullAiTransport;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One governed path to any AI provider.
 *
 * The rule that outranks everything in this epic: AI PROPOSES, A PERSON
 * DECIDES. Nothing here holds a command and nothing here sends to a customer.
 *
 * Most of what follows is about ABSENCE. A provider that is off, unreachable,
 * or forbidden from transmitting produces no error, no toast, no retry
 * control, no empty panel and no skeleton — the surface is simply complete
 * without it, which is what "assistive" means and why the whole product runs
 * with every capability switched off.
 */
final class AiConnectorTest extends TestCase
{
    use RefreshDatabase;

    private NullAiTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = $this->app->make(NullAiTransport::class);
        $this->app->instance(AiTransport::class, $this->transport);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    private function provider(): AiProvider
    {
        return $this->app->make(AiProvider::class);
    }

    /** Turns everything on, so the tests below are about what still refuses. */
    private function enableEverything(): void
    {
        $this->settings()->set(GuardedAiProvider::TRANSMISSION, TransmissionMode::Redacted->value, null);

        foreach (AiCapability::cases() as $capability) {
            $this->settings()->set($capability->setting(), true, null);
        }
    }

    public function test_everything_is_off_out_of_the_box(): void
    {
        /*
         * A deployment that installs this product and changes nothing has AI
         * off at the boundary AND off at every capability. Landing the
         * connector before any capability is the point of this story; landing
         * it live would not be.
         */
        $this->assertSame(
            TransmissionMode::Off->value,
            $this->settings()->get(GuardedAiProvider::TRANSMISSION),
        );

        foreach (AiCapability::cases() as $capability) {
            $this->assertFalse(
                (bool) $this->settings()->get($capability->setting()),
                "[{$capability->value}] is on by default.",
            );
        }
    }

    public function test_every_capability_degrades_to_absence_when_off(): void
    {
        $provider = $this->provider();

        // The contract's degraded value, one per capability, declared on the
        // port rather than decided by each caller.
        $this->assertNull($provider->summarise(['anything'], 'en'));
        $this->assertSame([], $provider->suggestReply(['anything'], 'en'));
        $this->assertNull($provider->proposeCategory('subject', 'body', [1 => 'Billing']));
        $this->assertSame([], $provider->suggestArticles('question', ['a' => 'Title']));

        $answer = $provider->answer('question', [], ['a' => 'Body'], 'en');
        $this->assertTrue($answer->handOff);
        $this->assertNull($answer->text);

        // And nothing was sent to find that out.
        $this->assertSame([], $this->transport->sent());
    }

    public function test_transmission_off_outranks_every_capability_switch(): void
    {
        foreach (AiCapability::cases() as $capability) {
            $this->settings()->set($capability->setting(), true, null);
        }

        $this->settings()->set(GuardedAiProvider::TRANSMISSION, TransmissionMode::Off->value, null);

        $this->assertNull($this->provider()->summarise(['anything'], 'en'));

        /*
         * "Nothing leaves this deployment" is a statement about the boundary,
         * not a shorthand for turning five things off — an administrator who
         * set it should not have to also remember the five.
         */
        $this->assertSame([], $this->transport->sent());
    }

    public function test_an_unknown_transmission_mode_is_refused_and_would_mean_off(): void
    {
        /*
         * Two layers, and both matter.
         *
         * The registry REFUSES a mode that is not one of the two, so the
         * setting cannot be put into a state nobody designed for.
         */
        $this->expectException(\App\Modules\Platform\Exceptions\ProblemException::class);

        $this->settings()->set(GuardedAiProvider::TRANSMISSION, 'sometimes', null);
    }

    public function test_a_corrupt_transmission_row_means_nothing_leaves(): void
    {
        $this->settings()->set(AiCapability::Summary->setting(), true, null);

        /*
         * A row the validator never saw — written by a migration, a seeder or
         * a console script straight into the table.
         *
         * What protects the deployment here is a CHAIN, and it is worth being
         * precise about which link does the work: the registry refuses to
         * return a stored value that fails its definition and hands back the
         * DEFAULT instead, and this setting's default is `off`. So the safety
         * comes from the default, not from the enum fallback in the guard —
         * which is why this test fails if the default is ever changed to
         * `redacted` and passes whatever the guard's fallback says.
         */
        \Illuminate\Support\Facades\DB::table('settings')
            ->where('key', GuardedAiProvider::TRANSMISSION)
            ->update(['value' => json_encode('sometimes')]);

        $this->app->forgetInstance(SettingsRegistry::class);
        $this->app->forgetInstance(AiProvider::class);

        $this->assertNull($this->provider()->summarise(['hello'], 'en'));
        $this->assertSame([], $this->transport->sent());
    }

    public function test_a_capability_can_be_switched_on_alone(): void
    {
        $this->settings()->set(GuardedAiProvider::TRANSMISSION, TransmissionMode::Redacted->value, null);
        $this->settings()->set(AiCapability::Summary->setting(), true, null);

        $this->provider()->summarise(['the invoice is wrong'], 'en');
        $this->provider()->suggestReply(['the invoice is wrong'], 'en');

        /*
         * One request, not two. Independently switchable means the other four
         * do not travel with the one that was turned on.
         */
        $this->assertCount(1, $this->transport->sent());
    }

    public function test_a_provider_that_answers_nothing_is_absent_not_broken(): void
    {
        $this->enableEverything();

        // The null transport answers null — which is what an unreachable
        // provider and a timed-out one both look like from here.
        $this->assertNull($this->provider()->summarise(['hello'], 'en'));
        $this->assertSame([], $this->provider()->suggestReply(['hello'], 'en'));
        $this->assertTrue($this->provider()->answer('q', [], ['a' => 'body'], 'en')->handOff);
    }

    public function test_a_provider_that_throws_is_absent_not_broken(): void
    {
        $this->enableEverything();

        $this->app->instance(AiTransport::class, new class implements AiTransport
        {
            public function complete(\App\Modules\Ai\Contracts\SanitisedPrompt $prompt, int $timeoutSeconds): ?string
            {
                throw new \RuntimeException('the provider fell over');
            }

            public function name(): string
            {
                return 'exploding';
            }
        });

        /*
         * A provider failing must not fail the screen it is decorating. An
         * agent reading a ticket does not need to know that a summary they
         * never saw could not be made.
         */
        $this->assertNull($this->provider()->summarise(['hello'], 'en'));
    }

    public function test_a_secret_is_never_transmitted(): void
    {
        $this->enableEverything();

        $sanitiser = $this->app->make(Sanitiser::class);

        $prompt = $sanitiser->prepare([
            'api_key' => 'sk-live-0000000000000000',
            'webhook_secret' => 'whsec_supersecret',
            'authorization' => 'Bearer abcdef123456',
            'complaint' => 'the invoice is wrong',
        ]);

        foreach (['sk-live', 'whsec_supersecret', 'abcdef123456'] as $secret) {
            $this->assertStringNotContainsString($secret, $prompt->text);
        }

        // Dropped entirely, not blanked: a line saying "api_key: [redacted]"
        // tells a model this deployment has one.
        $this->assertStringNotContainsString('api_key', $prompt->text);
        $this->assertStringContainsString('the invoice is wrong', $prompt->text);
    }

    public function test_an_attachments_bytes_are_never_transmitted(): void
    {
        $sanitiser = $this->app->make(Sanitiser::class);

        $bytes = "\x89PNG\r\n\x1a\nTHE-FILE-CONTENTS-0451";

        $prompt = $sanitiser->prepare([
            'attachment' => $bytes,
            'file_content' => $bytes,
            'complaint' => 'see the screenshot',
        ]);

        $this->assertStringNotContainsString('THE-FILE-CONTENTS-0451', $prompt->text);
        $this->assertStringNotContainsString('PNG', $prompt->text);
        $this->assertStringContainsString('see the screenshot', $prompt->text);
    }

    public function test_a_request_over_the_cap_sends_less_and_says_so(): void
    {
        $this->settings()->set(Sanitiser::MAX_CHARACTERS, 200, null);

        $sanitiser = $this->app->make(Sanitiser::class);

        $long = implode("\n", array_fill(0, 40, 'The parcel never arrived and nobody called.'));
        $prompt = $sanitiser->prepare(['complaint' => $long]);

        $this->assertLessThanOrEqual(200, mb_strlen($prompt->text));
        $this->assertTrue($prompt->shortened);

        /*
         * Marked, not silently cut. A model handed half a sentence finishes it
         * itself, and the answer an agent then reads is about something the
         * customer never said.
         */
        $this->assertStringContainsString('[omitted]', $prompt->text);
    }

    public function test_nothing_under_the_cap_is_touched(): void
    {
        $sanitiser = $this->app->make(Sanitiser::class);

        $prompt = $sanitiser->prepare(['complaint' => 'The parcel never arrived.']);

        $this->assertSame('The parcel never arrived.', $prompt->text);
        $this->assertFalse($prompt->shortened);
    }

    public function test_a_proposed_category_must_be_one_that_was_offered(): void
    {
        $this->enableEverything();

        $this->app->instance(AiTransport::class, new class implements AiTransport
        {
            public function complete(\App\Modules\Ai\Contracts\SanitisedPrompt $prompt, int $timeoutSeconds): ?string
            {
                // An id from another deployment, or an invented one.
                return '9999';
            }

            public function name(): string
            {
                return 'confident';
            }
        });

        /*
         * Checked here rather than in each caller, because every caller would
         * have to remember it — and the one that forgot would write an
         * invented category id onto a real ticket.
         */
        $this->assertNull($this->provider()->proposeCategory('s', 'b', [1 => 'Billing', 2 => 'Delivery']));
    }

    public function test_suggested_articles_are_a_subset_of_what_was_offered(): void
    {
        $this->enableEverything();

        $this->app->instance(AiTransport::class, new class implements AiTransport
        {
            public function complete(\App\Modules\Ai\Contracts\SanitisedPrompt $prompt, int $timeoutSeconds): ?string
            {
                return 'article-a article-invented';
            }

            public function name(): string
            {
                return 'confident';
            }
        });

        $this->assertSame(
            ['article-a'],
            $this->provider()->suggestArticles('q', ['article-a' => 'One', 'article-b' => 'Two']),
        );
    }

    public function test_the_chatbot_hands_off_when_there_is_nothing_published_to_answer_from(): void
    {
        $this->enableEverything();

        /*
         * The one place AI reaches a customer unattended, so the quickest to
         * give up. Answering from nothing is how a support bot invents policy.
         */
        $this->assertTrue($this->provider()->answer('q', [], [], 'en')->handOff);
        $this->assertSame([], $this->transport->sent());
    }

    public function test_swapping_the_provider_and_model_changes_no_behaviour(): void
    {
        $this->enableEverything();

        $before = $this->provider()->summarise(['hello'], 'en');

        $this->settings()->set(GuardedAiProvider::PROVIDER, 'someone-else', null);
        $this->settings()->set(GuardedAiProvider::MODEL, 'a-different-model', null);

        $this->assertSame($before, $this->provider()->summarise(['hello'], 'en'));
    }

    public function test_the_port_is_always_the_guard(): void
    {
        /*
         * Binding a transport directly to `AiProvider` would put a provider
         * behind the interface with no sanitiser, no capability switch, no
         * transmission mode and no timeout — every rule in this module gone,
         * in one line that looks like configuration.
         */
        $this->assertInstanceOf(GuardedAiProvider::class, $this->app->make(AiProvider::class));
    }
}
