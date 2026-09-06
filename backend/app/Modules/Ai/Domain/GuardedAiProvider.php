<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain;

use App\Modules\Ai\Contracts\AiCapability;
use App\Modules\Ai\Contracts\AiProvider;
use App\Modules\Ai\Contracts\AiTransport;
use App\Modules\Ai\Contracts\ChatbotAnswer;
use App\Modules\Ai\Domain\Sanitiser\Sanitiser;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only implementation of the port, and the only thing bound to it.
 *
 * Everything the product decided about AI lives here, once: which capabilities
 * are on, whether anything may leave the deployment at all, how long a person
 * is willing to wait, and what each capability returns when the answer is no.
 * Underneath it a transport has one method and knows none of that — which is
 * why swapping providers changes no behaviour on any surface.
 *
 * TIMEOUTS ARE IN-REQUEST AND HARD. A person is waiting for every one of these,
 * so none of it becomes a queued job and none of it holds a request open past
 * the limit. Past it, the capability is simply absent.
 *
 * FAILURE IS ABSENCE, never an error. A provider being down is not something a
 * customer's agent should be told about mid-ticket: there is no toast, no
 * retry control, no empty panel and no skeleton that never resolves. The
 * screen is complete without it. The only place a failure is recorded is the
 * log, where an administrator can find it.
 */
final class GuardedAiProvider implements AiProvider
{
    public const PROVIDER = 'ai.provider';

    public const MODEL = 'ai.model';

    public const TIMEOUT_SECONDS = 'ai.timeout_seconds';

    public const TRANSMISSION = 'ai.transmission';

    public function __construct(
        private readonly AiTransport $transport,
        private readonly Sanitiser $sanitiser,
        private readonly SettingsRegistry $settings,
    ) {}

    public function summarise(array $messages, string $locale): ?string
    {
        $answer = $this->ask(AiCapability::Summary, [
            'instruction' => $this->instruction('summarise', $locale),
            'conversation' => implode("\n", $messages),
        ]);

        return $answer === null || trim($answer) === '' ? null : $answer;
    }

    public function suggestReply(array $messages, string $locale): array
    {
        $answer = $this->ask(AiCapability::SuggestedReply, [
            'instruction' => $this->instruction('suggest_reply', $locale),
            'conversation' => implode("\n", $messages),
        ]);

        return $answer === null ? [] : array_values(array_filter(
            array_map(trim(...), explode("\n---\n", $answer)),
            static fn (string $line): bool => $line !== '',
        ));
    }

    public function proposeCategory(string $subject, string $body, array $categories): ?int
    {
        if ($categories === []) {
            // Nothing to choose from. Asking anyway would invite an answer
            // that is not one of the options.
            return null;
        }

        $answer = $this->ask(AiCapability::CategoryProposal, [
            'instruction' => $this->instruction('propose_category', 'en'),
            'options' => implode("\n", array_map(
                static fn (int $id, string $name): string => "{$id}: {$name}",
                array_keys($categories),
                array_values($categories),
            )),
            'subject' => $subject,
            'body' => $body,
        ]);

        if ($answer === null || preg_match('/\d+/', $answer, $found) !== 1) {
            return null;
        }

        $proposed = (int) $found[0];

        /*
         * Only an id that was offered. A model that answers with a category
         * from another deployment, or invents one, must not have that id
         * written onto a ticket — and the check belongs here rather than in
         * each caller, because every caller would have to remember it.
         */
        return array_key_exists($proposed, $categories) ? $proposed : null;
    }

    public function suggestArticles(string $question, array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $answer = $this->ask(AiCapability::SuggestedArticles, [
            'instruction' => $this->instruction('suggest_articles', 'en'),
            'candidates' => implode("\n", array_map(
                static fn (string $id, string $title): string => "{$id}: {$title}",
                array_keys($candidates),
                array_values($candidates),
            )),
            'question' => $question,
        ]);

        if ($answer === null) {
            return [];
        }

        // A subset of what was offered, never an id that was not.
        return array_values(array_filter(
            array_map(trim(...), preg_split('/[\s,]+/', $answer) ?: []),
            static fn (string $id): bool => $id !== '' && array_key_exists($id, $candidates),
        ));
    }

    public function answer(string $question, array $history, array $articles, string $locale): ChatbotAnswer
    {
        if ($articles === []) {
            /*
             * Nothing published to answer from. The chatbot is the one place
             * AI reaches a customer unattended, so it is the quickest to give
             * up — answering from nothing is how a support bot invents policy.
             */
            return ChatbotAnswer::handOff();
        }

        $answer = $this->ask(AiCapability::Chatbot, [
            'instruction' => $this->instruction('answer', $locale),
            'articles' => implode("\n\n", $articles),
            'conversation' => implode("\n", $history),
            'question' => $question,
        ]);

        if ($answer === null || trim($answer) === '') {
            return ChatbotAnswer::handOff();
        }

        return new ChatbotAnswer($answer, handOff: false, articleIds: array_keys($articles));
    }

    /**
     * The one path to the transport. Every capability goes through it.
     *
     * @param  array<string, string>  $parts
     */
    private function ask(AiCapability $capability, array $parts): ?string
    {
        if (! $this->isOn($capability)) {
            return null;
        }

        try {
            /*
             * Sanitised before anything else touches it, and the transport's
             * signature will not accept anything that was not.
             */
            $prompt = $this->sanitiser->prepare($parts);

            if (trim($prompt->text) === '') {
                // Everything was redacted or omitted. Sending an empty prompt
                // would spend a request to be told nothing.
                return null;
            }

            return $this->transport->complete($prompt, $this->timeout());
        } catch (Throwable $e) {
            /*
             * Swallowed, and said so. A provider failing must not fail the
             * screen it is decorating — an agent reading a ticket does not
             * need to know that a summary they never saw could not be made.
             */
            Log::warning('An AI capability was unavailable.', [
                'capability' => $capability->value,
                'provider' => $this->transport->name(),
                'reason' => $e->getMessage(),
                'consequence' => 'The surface renders without it. Nothing else is affected.',
            ]);

            return null;
        }
    }

    private function isOn(AiCapability $capability): bool
    {
        if ($this->mode() === TransmissionMode::Off) {
            /*
             * Checked FIRST, and it outranks every capability switch. "Nothing
             * leaves this deployment" is a statement about the boundary, not a
             * shorthand for turning five things off — and an administrator who
             * set it should not have to also remember the five.
             */
            return false;
        }

        return (bool) $this->settings->get($capability->setting());
    }

    private function mode(): TransmissionMode
    {
        /*
         * The `??` is defence in depth and is not what actually protects a
         * deployment with a corrupt row — the registry already refuses to
         * return a stored value that fails its definition and hands back the
         * DEFAULT, which for this setting is `off`.
         *
         * Kept anyway, because the two guarantees have different lifetimes: a
         * future registry that returned raw values, or a caller that read the
         * table directly, would land here, and the answer to "we cannot read
         * this" must be the one that sends nothing.
         */
        return TransmissionMode::tryFrom((string) $this->settings->get(self::TRANSMISSION))
            ?? TransmissionMode::Off;
    }

    private function timeout(): int
    {
        return max(1, (int) $this->settings->get(self::TIMEOUT_SECONDS));
    }

    /**
     * What the model is being asked to do, in the reader's language.
     *
     * Here rather than in each capability's caller, so the wording is one
     * thing to change and a capability cannot quietly ask for something the
     * product did not agree to.
     */
    private function instruction(string $key, string $locale): string
    {
        return __("ai.instruction.{$key}", ['model' => (string) $this->settings->get(self::MODEL)], $locale);
    }
}
