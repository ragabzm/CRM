<?php

declare(strict_types=1);

namespace App\Modules\Ai;

use App\Modules\Ai\Contracts\AiCapability;
use App\Modules\Ai\Contracts\AiProvider;
use App\Modules\Ai\Contracts\AiTransport;
use App\Modules\Ai\Domain\GuardedAiProvider;
use App\Modules\Ai\Domain\Sanitiser\Sanitiser;
use App\Modules\Ai\Domain\TransmissionMode;
use App\Modules\Ai\Infrastructure\NullAiTransport;
use App\Modules\Platform\Support\Settings\RegistersSettings;
use App\Modules\Platform\Support\Settings\SettingDefinition;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Platform\Support\Settings\SettingType;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the one AI port, and wires it to nothing by default.
 *
 * Every default in this file says off. A deployment that installs this
 * product and changes no settings has AI switched off at the boundary
 * (`transmission = off`), off at every one of the five capabilities, and
 * pointed at a transport that answers nothing. Landing the connector before
 * any capability is the point of this story; landing it live would not be.
 */
final class AiServiceProvider extends ServiceProvider implements RegistersSettings
{
    public function register(): void
    {
        /*
         * The default transport, replaceable by binding this interface.
         *
         * `scoped` rather than `singleton`: the null transport records what it
         * was asked to send, and under a persistent worker a singleton would
         * accumulate every request the process ever made.
         */
        $this->app->scoped(AiTransport::class, NullAiTransport::class);

        /*
         * The ONE binding of the port, and it is always the guard.
         *
         * Binding a transport directly to `AiProvider` would put a provider
         * behind the interface with no sanitiser, no capability switch, no
         * transmission mode and no timeout — every rule in this module, gone,
         * in one line that looks like configuration.
         * `SanitiserIsUnbypassableTest` fails the build if it happens.
         */
        $this->app->scoped(AiProvider::class, static fn ($app): AiProvider => new GuardedAiProvider(
            $app->make(AiTransport::class),
            $app->make(Sanitiser::class),
            $app->make(SettingsRegistry::class),
        ));
    }

    public function registerSettings(SettingsRegistry $registry): void
    {
        /*
         * The boundary switch, and it outranks the five below it.
         *
         * `off` by default. An administrator turning on a capability has made
         * a decision about a feature; an administrator turning THIS on has
         * made a decision about where their customers' words go, and the two
         * should not be the same click.
         */
        $registry->register(new SettingDefinition(
            key: GuardedAiProvider::TRANSMISSION,
            type: SettingType::Enum,
            default: TransmissionMode::Off->value,
            allowedValues: TransmissionMode::values(),
            summary: 'Whether anything may be sent to an AI provider at all. Off means nothing leaves this deployment.',
        ));

        $registry->register(new SettingDefinition(
            key: GuardedAiProvider::PROVIDER,
            type: SettingType::String,
            default: 'null',
            summary: 'Which AI provider is used. Changing it changes no behaviour on any surface.',
        ));

        $registry->register(new SettingDefinition(
            key: GuardedAiProvider::MODEL,
            type: SettingType::String,
            default: '',
            summary: 'The model name passed to the provider.',
        ));

        $registry->register(new SettingDefinition(
            key: GuardedAiProvider::TIMEOUT_SECONDS,
            type: SettingType::Int,
            default: 8,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 1 && $v <= 30
                ? true
                /*
                 * Thirty seconds is already far longer than anybody waits. The
                 * cap is here because this runs IN-REQUEST — a person is
                 * watching a screen — and a minute-long timeout would hold
                 * their request open for a summary they stopped wanting.
                 */
                : 'The AI timeout must be between 1 and 30 seconds — a person is waiting for it.',
            summary: 'How long a request waits for the provider before the AI surface is simply absent.',
        ));

        $registry->register(new SettingDefinition(
            key: Sanitiser::MAX_CHARACTERS,
            type: SettingType::Int,
            default: 6000,
            validator: static fn (mixed $v): true|string => is_int($v) && $v >= 200 && $v <= 100000
                ? true
                : 'The AI content cap must be between 200 and 100,000 characters.',
            summary: 'The most content one AI request may carry. Over it, less is sent — never more.',
        ));

        /*
         * One switch per capability, derived from the enum so that adding a
         * case adds its switch. A capability with no setting would be AI
         * running in a deployment that believes it has AI disabled.
         */
        foreach (AiCapability::cases() as $capability) {
            $registry->register(new SettingDefinition(
                key: $capability->setting(),
                type: SettingType::Bool,
                default: false,
                summary: self::SUMMARIES[$capability->value],
            ));
        }
    }

    /**
     * What each switch does, in the words an administrator reads.
     *
     * @var array<string, string>
     */
    private const SUMMARIES = [
        'summary' => 'Offer an agent a short account of a long ticket. Off by default.',
        'suggested_reply' => 'Offer an agent draft wording to edit. Nothing is ever sent unattended.',
        'category_proposal' => 'Propose a category an agent may accept. Nothing is applied automatically.',
        'suggested_articles' => 'Offer published articles that may answer a question.',
        'chatbot' => 'Let the chat widget answer from published articles, and hand off when it cannot.',
    ];
}
