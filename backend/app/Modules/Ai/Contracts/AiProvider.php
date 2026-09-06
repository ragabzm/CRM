<?php

declare(strict_types=1);

namespace App\Modules\Ai\Contracts;

/**
 * The one way anything in this product talks to an AI provider.
 *
 * There is exactly one of these, and no feature calls a vendor SDK directly.
 * That is not a style preference: the sanitiser, the capability switches, the
 * timeout and the external-transmission mode all live behind this interface,
 * and a second path to a provider is a path around all four at once.
 * `OneAiPortTest` fails the build on a provider SDK imported outside this
 * module.
 *
 * THE DEGRADED RETURN VALUE OF EVERY METHOD IS PART OF THE CONTRACT, and it is
 * declared here rather than left to each caller. No vendor SDK defines what
 * "the provider is down" should mean for a support desk; that is a product
 * decision (BR-18), and this is where it is written down:
 *
 *   - no summary          → null
 *   - no suggestions      → an empty list
 *   - no proposed category → null
 *   - no suggested articles → an empty list
 *   - chatbot             → an answer whose `handOff` is true
 *
 * Every one of those renders as ABSENCE on the surface above — no error, no
 * toast, no retry control, no empty panel, no skeleton that never resolves.
 * The screen is complete without it, which is what "assistive" means and why
 * the whole product runs with every capability switched off.
 *
 * Nothing here holds a command and nothing here sends to a customer. AI
 * proposes; a person decides.
 */
interface AiProvider
{
    /**
     * A short account of a conversation, for an agent picking it up cold.
     *
     * @param  list<string>  $messages  Oldest first, already the caller's minimum.
     * @return string|null Null when the capability is off, transmission is
     *                     disabled, or the provider did not answer in time.
     */
    public function summarise(array $messages, string $locale): ?string;

    /**
     * Draft wording for a reply. The agent edits and sends; nothing is sent
     * on their behalf.
     *
     * @param  list<string>  $messages
     * @return list<string> Empty when unavailable.
     */
    public function suggestReply(array $messages, string $locale): array;

    /**
     * A category the agent may accept, from the list they are choosing from.
     *
     * @param  array<int, string>  $categories  id => name, the only ones allowed.
     * @return int|null The proposed category id, or null. Nothing is applied
     *                  automatically, so there is no confidence to threshold.
     */
    public function proposeCategory(string $subject, string $body, array $categories): ?int;

    /**
     * Which of these candidate articles actually answer the question.
     *
     * The CANDIDATES are supplied by the caller from Knowledge's own Postgres
     * search — there is no vector store, no embeddings index and no retrieval
     * infrastructure here.
     *
     * @param  array<string, string>  $candidates  id => title.
     * @return list<string> Article ids, a subset of the candidates. Empty when
     *                      unavailable, and never an id that was not offered.
     */
    public function suggestArticles(string $question, array $candidates): array;

    /**
     * Answers a customer from published articles, or hands off.
     *
     * @param  list<string>  $history  The conversation so far.
     * @param  array<string, string>  $articles  id => body, published only.
     */
    public function answer(string $question, array $history, array $articles, string $locale): ChatbotAnswer;
}
