<?php

declare(strict_types=1);

namespace App\Modules\Ai\Contracts;

/**
 * What the chatbot came back with, or the fact that it could not.
 *
 * `handOff` is the important field and it defaults to the safe answer. A
 * chatbot that stays in the conversation while unsure is a chatbot that
 * invents — and the single place in this product where AI reaches a customer
 * unattended is the one place that must be quickest to give up.
 */
final readonly class ChatbotAnswer
{
    /**
     * @param  list<string>  $articleIds  What the answer was drawn from, so a
     *                                    human can check it against the source.
     */
    public function __construct(
        public ?string $text,
        public bool $handOff,
        public array $articleIds = [],
    ) {}

    /**
     * The degraded answer: say nothing, and put a person on it.
     *
     * Named rather than constructed at each call site, so "what happens when
     * AI is unavailable?" has one answer that can be read in one place.
     */
    public static function handOff(): self
    {
        return new self(null, true);
    }
}
