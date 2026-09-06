<?php

declare(strict_types=1);

namespace App\Modules\Assist\Domain;

use App\Modules\Ai\Contracts\AiProvider;
use App\Modules\Knowledge\Domain\Search\ArticleSearch;
use Illuminate\Support\Facades\DB;

/**
 * The three things the machine may offer an agent about one ticket.
 *
 * A summary, a draft reply, a proposed category — and beside them, ranked
 * articles. Every one of them is a PROPOSAL returned to a person. Nothing in
 * this class holds a Tickets command, nothing writes, and nothing sends: the
 * agent confirming a category does it through the ordinary command path with
 * their own name on it, and the agent sending a reply does it from the
 * composer they just edited.
 *
 * EACH ASSIST SENDS THE MINIMUM. The summary sends the thread. The category
 * proposal sends the subject and the description and nothing else — an agent
 * asking which box a request belongs in does not need six months of
 * back-and-forth leaving the building to answer it. Article ranking sends the
 * ticket text and the candidates Knowledge's own search returned.
 *
 * That last one is a TWO-STEP on purpose: Postgres finds the candidates, the
 * model ranks them. It is what keeps suggestions inside the knowledge base —
 * a model asked to suggest an article from nothing will write one — and it is
 * why there is no vector store and no embedding index in this product.
 */
final class TicketAssists
{
    /** How many articles Knowledge's search offers the ranker. */
    private const CANDIDATES = 10;

    /**
     * How many of the ticket's own words are searched for.
     *
     * Bounded, because this is one query each and a long ticket has hundreds
     * of words. Six is enough to find the article and few enough that the
     * whole step stays well inside the time an agent is waiting.
     */
    private const TERMS = 6;

    public function __construct(
        private readonly AiProvider $ai,
        private readonly ArticleSearch $articles,
    ) {}

    /**
     * A short account of the conversation, computed now and never stored.
     *
     * Per request, so a stale summary of a conversation that has moved on
     * cannot exist. Regenerating is a fresh call rather than a cache
     * invalidation — there is no cache to invalidate, which is the only
     * version of that guarantee nobody can accidentally break.
     */
    public function summarise(string $ticketId, string $locale): ?string
    {
        $messages = $this->thread($ticketId);

        if ($messages === []) {
            return null;
        }

        return $this->ai->summarise($messages, $locale);
    }

    /**
     * Draft wording for the composer, drawn from the thread and the articles.
     *
     * @return list<string>
     */
    public function suggestReply(string $ticketId, string $locale): array
    {
        $messages = $this->thread($ticketId);

        if ($messages === []) {
            return [];
        }

        return $this->ai->suggestReply($messages, $locale);
    }

    /**
     * A category an agent may confirm, from the list they are choosing from.
     *
     * @return array{category_id: int, name: string}|null
     */
    public function proposeCategory(string $ticketId, string $locale): ?array
    {
        $ticket = DB::table('tickets')->where('id', $ticketId)->first(['subject', 'description']);

        if ($ticket === null) {
            return null;
        }

        $column = $locale === 'ar' ? 'name_ar' : 'name_en';

        /** @var array<int, string> $categories */
        $categories = DB::table('ticket_categories')
            ->orderBy('sort_order')
            ->pluck($column, 'id')
            ->map(strval(...))
            ->all();

        /*
         * Subject and description only. The whole thread would send a
         * customer's later messages — often the emotional ones — to answer a
         * question about filing.
         */
        $proposed = $this->ai->proposeCategory(
            (string) $ticket->subject,
            (string) $ticket->description,
            $categories,
        );

        if ($proposed === null || ! array_key_exists($proposed, $categories)) {
            return null;
        }

        return ['category_id' => $proposed, 'name' => $categories[$proposed]];
    }

    /**
     * Articles that may answer this, ranked.
     *
     * INTERNAL ARTICLES INCLUDED. This surface is the agent's, and an internal
     * article is written precisely so an agent can read it — passing
     * `customerVisibleOnly: false` is the point rather than an oversight, and
     * nothing in this story reaches a customer.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestArticles(string $ticketId, string $locale): array
    {
        $ticket = DB::table('tickets')->where('id', $ticketId)->first(['subject', 'description']);

        if ($ticket === null) {
            return [];
        }

        $question = trim($ticket->subject.' '.$ticket->description);

        /*
         * Step one: Knowledge's own search, asked ONE TERM AT A TIME.
         *
         * Handing it the whole ticket as a search string does not work and
         * would not be right if it did. On the portable path the search is
         * substring containment, so a paragraph matches an article only if the
         * article contains that exact paragraph — which is never. On Postgres
         * it tokenises and would return something, so the two drivers would
         * quietly disagree, and the suite runs on the one that returns
         * nothing.
         *
         * Searching by term is also simply a better query: an agent looking
         * for the refund article wants articles about refunds, not articles
         * that happen to contain the customer's whole sentence.
         */
        $candidates = $this->candidates($question, $locale);

        if ($candidates === []) {
            return [];
        }

        $byId = [];

        foreach ($candidates as $candidate) {
            $byId[(string) $candidate['id']] = (string) ($candidate['title'] ?? '');
        }

        // Step two: the model ranks what it was given, and can return nothing
        // it was not given.
        $ranked = $this->ai->suggestArticles($question, $byId);

        if ($ranked === []) {
            return [];
        }

        $lookup = [];

        foreach ($candidates as $candidate) {
            $lookup[(string) $candidate['id']] = $candidate;
        }

        return array_values(array_filter(array_map(
            static fn (string $id): ?array => $lookup[$id] ?? null,
            $ranked,
        )));
    }

    /**
     * Candidate articles, gathered a term at a time and merged.
     *
     * Deterministic and bounded: the longest few words the ticket actually
     * uses, each searched once, merged in the order they were found and cut at
     * the candidate limit. Long words carry the meaning — "refund" and
     * "duplicate" find the article; "the" and "was" find everything.
     *
     * @return list<array<string, mixed>>
     */
    private function candidates(string $question, string $locale): array
    {
        $found = [];

        foreach ($this->terms($question) as $term) {
            foreach ($this->articles->search($term, $locale, false, self::CANDIDATES) as $article) {
                $found[(string) $article['id']] = $article;

                if (count($found) >= self::CANDIDATES) {
                    return array_values($found);
                }
            }
        }

        return array_values($found);
    }

    /**
     * The words worth searching for.
     *
     * @return list<string>
     */
    private function terms(string $question): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)) ?: [];

        /*
         * Four characters and up, in both scripts. Short words are the ones
         * every article contains, and a candidate set of "everything" is a
         * candidate set the ranker has to do the search's job on.
         */
        $words = array_values(array_unique(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= 4,
        )));

        // Longest first: the specific words are the long ones.
        usort($words, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_slice($words, 0, self::TERMS);
    }

    /**
     * The conversation, oldest first, as plain lines.
     *
     * INTERNAL NOTES EXCLUDED. A colleague's private remark about the customer
     * is the one thing on a ticket that must not leave the building, and
     * filtering it here — at the query — means it cannot arrive in a prompt
     * because somebody later added a field.
     *
     * @return list<string>
     */
    private function thread(string $ticketId): array
    {
        return DB::table('ticket_messages')
            ->where('ticket_id', $ticketId)
            ->whereIn('direction', ['inbound', 'outbound'])
            ->orderBy('sent_at')
            ->orderBy('id')
            ->pluck('body')
            ->map(static fn (mixed $body): string => (string) $body)
            ->all();
    }
}
