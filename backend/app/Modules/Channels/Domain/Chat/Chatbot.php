<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use App\Modules\Ai\Contracts\AiProvider;
use App\Modules\Knowledge\Domain\Search\ArticleSearch;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Answers from the help centre, and fetches a person the moment it cannot.
 *
 * The single place in this product where a machine reaches a customer without
 * a colleague having read what it wrote — which is why every rule below leans
 * the same way. WHEN IN DOUBT, HAND OFF. An unnecessary handoff costs an agent
 * a minute; a confident wrong answer costs the customer, and they will not
 * come back to tell us it was wrong.
 *
 * The "cannot answer" condition is explicit and conservative, and there are
 * exactly four ways in:
 *
 *   1. The capability is off. Then the customer is offered a person straight
 *      away, with no mention of a switched-off feature.
 *   2. No citable public article. An answer with nothing behind it is an
 *      answer this product cannot stand behind.
 *   3. The port returned its degraded value — unreachable, timed out, or
 *      transmission disabled. Immediate, and the customer is told a person is
 *      coming rather than shown an error about a provider they have never
 *      heard of.
 *   4. The customer asked for a person. That one needs no cleverness.
 *
 * IT HOLDS NO COMMAND. It never changes a status, a priority, a category or an
 * assignee, never sends an email, and never resolves or closes anything. The
 * only thing it writes is a message on the conversation's own ticket, through
 * the same `AppendMessage` a person uses — it does not invent a second way a
 * chat becomes a ticket.
 */
final class Chatbot
{
    /** How many published articles the answer may be drawn from. */
    private const CANDIDATES = 4;

    /**
     * Words that mean "stop talking to me and get somebody".
     *
     * Deliberately short and deliberately generous: matching too eagerly costs
     * an agent a minute, and matching too rarely leaves somebody arguing with
     * a machine. There is no intent model here and there is not going to be
     * one — that would be a second thing that can be wrong about the one
     * request a customer has made explicitly.
     *
     * @var list<string>
     */
    private const ASKS_FOR_A_PERSON = [
        'agent', 'human', 'person', 'somebody', 'someone', 'representative',
        'talk to', 'speak to', 'real person', 'operator',
        'موظف', 'شخص', 'حد', 'انسان', 'إنسان', 'بشر', 'مسؤول', 'ممثل',
    ];

    public function __construct(
        private readonly AiProvider $ai,
        private readonly ArticleSearch $articles,
        private readonly AppendMessage $appendMessage,
    ) {}

    /**
     * Takes one visitor turn and either answers it or hands it over.
     *
     * @return array{answered: bool, handed_off: bool}
     */
    public function respondTo(ChatConversation $conversation, string $question, string $locale): array
    {
        if ($conversation->handed_off_at !== null || $conversation->taken_by !== null) {
            /*
             * A person already has it. The chatbot does not talk over a
             * colleague who is mid-conversation, and it does not answer after
             * a handoff — from the customer's point of view somebody arrived,
             * and a machine chiming in afterwards reads as being passed back.
             */
            return ['answered' => false, 'handed_off' => false];
        }

        $ticketId = $conversation->ticket_id === null ? null : (string) $conversation->ticket_id;

        if ($ticketId === null) {
            return ['answered' => false, 'handed_off' => false];
        }

        if ($this->asksForAPerson($question)) {
            return $this->handOff($conversation, $ticketId, $locale);
        }

        $articles = $this->publishedArticles($question, $locale);

        if ($articles === []) {
            // Nothing citable. An answer with nothing behind it is an answer
            // this product cannot stand behind.
            return $this->handOff($conversation, $ticketId, $locale);
        }

        try {
            $answer = $this->ai->answer($question, $this->history($ticketId), $this->bodies($articles), $locale);
        } catch (Throwable) {
            /*
             * Swallowed and handed off. The customer is told a person is
             * coming — never that a provider failed, which is a fact about our
             * infrastructure that they can do nothing with.
             */
            return $this->handOff($conversation, $ticketId, $locale);
        }

        if ($answer->handOff || $answer->text === null || trim($answer->text) === '') {
            return $this->handOff($conversation, $ticketId, $locale);
        }

        $this->say($ticketId, $this->withCitations($answer->text, $answer->articleIds, $articles, $locale));

        return ['answered' => true, 'handed_off' => false];
    }

    /**
     * Puts the conversation on the waiting list and says so.
     *
     * @return array{answered: bool, handed_off: bool}
     */
    public function handOff(ChatConversation $conversation, string $ticketId, string $locale): array
    {
        /*
         * The conditional update is the claim. Two turns arriving together —
         * a customer typing twice — must produce ONE handoff and one message,
         * not two of each on the ticket a colleague is about to read.
         */
        $marked = DB::table('chat_conversations')
            ->where('id', $conversation->getKey())
            ->whereNull('handed_off_at')
            ->update(['handed_off_at' => now(), 'updated_at' => now()]);

        if ($marked === 0) {
            return ['answered' => false, 'handed_off' => false];
        }

        /*
         * "A person is on their way", and nothing else. No error, no retry, no
         * stack of apologies — and no mention of a capability being off, which
         * would tell a customer about a switch they cannot reach.
         */
        $this->say($ticketId, __('channels.chat.handing_off', [], $locale));

        return ['answered' => false, 'handed_off' => true];
    }

    /**
     * Public, published articles only — narrowed at the QUERY.
     *
     * `customerVisibleOnly: true` is what excludes internal, draft and
     * archived articles, and it is passed here rather than filtered
     * afterwards: a filter applied after the rows are loaded is a filter one
     * refactor away from not being applied.
     *
     * @return list<array<string, mixed>>
     */
    private function publishedArticles(string $question, string $locale): array
    {
        $terms = $this->terms($question);
        $found = [];

        foreach ($terms as $term) {
            foreach ($this->articles->search($term, $locale, true, self::CANDIDATES) as $article) {
                $found[(string) $article['id']] = $article;

                if (count($found) >= self::CANDIDATES) {
                    return array_values($found);
                }
            }
        }

        return array_values($found);
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @return array<string, string> id => body
     */
    private function bodies(array $articles): array
    {
        $ids = array_map(static fn (array $a): string => (string) $a['id'], $articles);

        if ($ids === []) {
            return [];
        }

        $bodies = DB::table('article_translations')
            ->whereIn('article_id', $ids)
            ->get(['article_id', 'title', 'body']);

        $out = [];

        foreach ($bodies as $row) {
            $out[(string) $row->article_id] = (string) $row->title."\n".(string) $row->body;
        }

        return $out;
    }

    /**
     * The answer with the article it came from, as a link the reader can open.
     *
     * The link is Story 8.2's stable id-keyed help-centre URL, so an article
     * that is later retitled still resolves from an old transcript. A title
     * pasted into the text would rot the moment somebody edited it.
     *
     * @param  list<string>  $citedIds
     * @param  list<array<string, mixed>>  $articles
     */
    private function withCitations(string $answer, array $citedIds, array $articles, string $locale): string
    {
        $titles = [];

        foreach ($articles as $article) {
            $titles[(string) $article['id']] = (string) ($article['title'] ?? '');
        }

        $lines = [];

        foreach ($citedIds as $id) {
            if (! array_key_exists($id, $titles)) {
                /*
                 * Never an article that was not among the candidates. The
                 * widget must not receive an id the search did not return —
                 * that is how a customer ends up sent to an internal article.
                 */
                continue;
            }

            $lines[] = $titles[$id].' — '.rtrim((string) config('app.frontend_url'), '/').'/portal/help/'.$id;
        }

        if ($lines === []) {
            return $answer;
        }

        return $answer."\n\n".__('channels.chat.answered_from', [], $locale)."\n".implode("\n", $lines);
    }

    /**
     * What has been said so far, customer and assistant alike.
     *
     * @return list<string>
     */
    private function history(string $ticketId): array
    {
        return DB::table('ticket_messages')
            ->where('ticket_id', $ticketId)
            ->whereIn('direction', ['inbound', 'outbound'])
            ->orderBy('sent_at')
            ->orderBy('id')
            ->limit(20)
            ->pluck('body')
            ->map(static fn (mixed $body): string => (string) $body)
            ->all();
    }

    private function say(string $ticketId, string $body): void
    {
        /*
         * Through `AppendMessage`, like everything else. The chatbot does not
         * write ticket rows and does not invent a second way a chat becomes a
         * ticket — and the actor is a SYSTEM actor labelled `chatbot`, so the
         * transcript says what answered and keeps saying it afterwards.
         */
        $this->appendMessage->handle(
            Actor::chatbot('chatbot'),
            $ticketId,
            MessageDirection::Outbound,
            $body,
        );
    }

    private function asksForAPerson(string $question): bool
    {
        $lower = mb_strtolower($question);

        foreach (self::ASKS_FOR_A_PERSON as $phrase) {
            if (str_contains($lower, mb_strtolower($phrase))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function terms(string $question): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)) ?: [];

        $words = array_values(array_unique(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= 4,
        )));

        usort($words, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_slice($words, 0, 6);
    }
}
