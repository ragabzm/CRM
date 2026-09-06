<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Channels\Domain\Chat\ChatConversation;
use App\Modules\Channels\Domain\Chat\ChatSettings;
use App\Modules\Channels\Domain\Chat\ChatToken;
use App\Modules\Channels\Domain\Chat\EmbedOrigins;
use App\Modules\Channels\Domain\Chat\EndConversation;
use App\Modules\Channels\Domain\Chat\PostVisitorMessage;
use App\Modules\Channels\Domain\Chat\StartConversation;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The four things the widget can do, and nothing else.
 *
 * Start a conversation, say something, read what has been said, close it. No
 * ticket lookup, no customer lookup, no list of conversations, no search. A
 * visitor's token cannot reach any of those because there is no endpoint here
 * that offers them — which is a stronger guarantee than a token that could
 * reach them and is checked each time.
 *
 * THE TOKEN TRAVELS IN A COOKIE, not in web storage. The story forbids
 * `localStorage` and `sessionStorage`, and a token held only in the iframe's
 * memory would not survive the visitor reloading the page — which the story
 * also requires. An http-only cookie is the one mechanism that satisfies both:
 * the browser holds it, no JavaScript on either side can read it, and it dies
 * with the conversation.
 *
 * The consequence worth stating: in a third-party embed this is a third-party
 * cookie, and a browser that blocks those will start a fresh conversation
 * after a reload rather than rejoining. The visitor loses nothing they typed —
 * the previous conversation is already a ticket — and the alternative the
 * story rules out is worse.
 */
final class ChatWidgetController extends Controller
{
    /**
     * Named so it cannot collide with the application's own cookies, and
     * pathed so it is not sent on any request that is not chat.
     */
    private const COOKIE = 'chat_conversation_token';

    public function __construct(
        private readonly StartConversation $start,
        private readonly PostVisitorMessage $post,
        private readonly EndConversation $end,
        private readonly ChatSettings $settings,
    ) {}

    /**
     * The origins allowed to embed the widget.
     *
     * Public, and it gives nothing away: it is the list of sites that may
     * frame a page they can already load. The frontend reads it to build the
     * `frame-ancestors` policy on the frame document, which is the half of
     * this rule a browser actually enforces.
     */
    public function embedOrigins(EmbedOrigins $origins): JsonResponse
    {
        return new JsonResponse(['data' => ['origins' => $origins->allowed()]]);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visitor_name' => ['nullable', 'string', 'max:120'],
            'visitor_identifier' => ['nullable', 'string', 'max:320'],
        ]);

        $started = $this->start->handle(
            /*
             * The site the widget is embedded on, as the frame reports it.
             *
             * NOT the `Origin` header: this request comes from the iframe,
             * whose origin is ours, so the header says nothing about the
             * embedding page. The loader reads `location.origin` on the host
             * page and passes it down.
             *
             * That makes this value CLAIMED rather than observed, and it is
             * treated accordingly — it stops a casual copy-paste of the loader
             * onto an unlisted site, and nothing more. The rule a browser
             * enforces is `frame-ancestors` on the frame document, which no
             * script on the host page can talk its way past.
             */
            $request->headers->get('X-Chat-Embed-Origin') ?? $request->headers->get('Origin'),
            is_string($data['visitor_name'] ?? null) ? $data['visitor_name'] : null,
            is_string($data['visitor_identifier'] ?? null) ? $data['visitor_identifier'] : null,
        );

        $conversation = $started['conversation'];

        return $this->withToken(
            new JsonResponse([
                'data' => [
                    'id' => (string) $conversation->getKey(),
                    'poll_seconds' => $this->settings->pollSeconds(),
                    'expires_at' => $conversation->token_expires_at->toIso8601ZuluString(),
                    'state' => $conversation->state(),
                ],
            ], 201),
            $started['token'],
        );
    }

    /**
     * Rejoins the conversation the cookie names, if it is still open.
     *
     * This is what makes a reload continue rather than restart. It answers
     * 404 rather than an error when there is nothing to rejoin, because "you
     * have no conversation" is the ordinary case for anybody opening the
     * widget for the first time.
     */
    public function current(Request $request): JsonResponse
    {
        $conversation = $this->fromCookie($request);

        return new JsonResponse([
            'data' => [
                'id' => (string) $conversation->getKey(),
                'poll_seconds' => $this->settings->pollSeconds(),
                'expires_at' => $conversation->token_expires_at->toIso8601ZuluString(),
                'state' => $conversation->state(),
            ],
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = $this->fromCookie($request);

        $this->post->handle($conversation, (string) $data['body']);

        return new JsonResponse(['data' => $this->transcript($conversation->refresh())]);
    }

    /**
     * Everything said so far, newest last.
     *
     * The whole transcript every time rather than a cursor. A chat is a
     * handful of short messages, the widget is redrawing a list it already
     * holds, and a `since=` parameter is a synchronisation layer with an
     * off-by-one that shows up as a message nobody ever sees.
     */
    public function messages(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->transcript($this->fromCookie($request))]);
    }

    public function close(Request $request): JsonResponse
    {
        $conversation = $this->end->handle($this->fromCookie($request));

        return new JsonResponse(['data' => ['state' => $conversation->state()]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transcript(ChatConversation $conversation): array
    {
        $messages = $conversation->ticket_id === null ? collect() : DB::table('ticket_messages')
            ->where('ticket_id', $conversation->ticket_id)
            /*
             * NEVER an internal note. A colleague's private remark about the
             * visitor is the one thing on a ticket that must not cross this
             * boundary, and filtering it here — at the query, not in the shape
             * — means a field cannot be added later that leaks it.
             */
            ->whereIn('direction', ['inbound', 'outbound'])
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get(['id', 'direction', 'body', 'author_type', 'author_name', 'sent_at']);

        return [
            'state' => $conversation->state(),
            'poll_seconds' => $this->settings->pollSeconds(),
            'taken' => $conversation->taken_by !== null,
            'messages' => $messages->map(static function (object $m): array {
                /*
                 * THREE speakers, not two.
                 *
                 * "them", "us" and the assistant — the visitor is not reading
                 * a ticket, and the one thing they must be able to tell is
                 * whether a person wrote this. The chatbot's turns are system
                 * messages labelled `chatbot`, and that label is what
                 * distinguishes them here and in the ticket a colleague reads
                 * afterwards.
                 */
                $from = match (true) {
                    $m->direction === 'inbound' => 'visitor',
                    $m->author_type === 'system' && (string) $m->author_name === Actor::CHATBOT => 'assistant',
                    default => 'agent',
                };

                return [
                    'id' => (string) $m->id,
                    'from' => $from,
                    'body' => (string) $m->body,
                    // No name for the machine: it never presents itself as a
                    // person, and a name is the first thing that would.
                    'author_name' => $from === 'agent' ? (string) $m->author_name : null,
                    'sent_at' => (string) $m->sent_at,
                ];
            })->all(),
        ];
    }

    /**
     * The conversation this browser holds a token for.
     *
     * Looked up BY HASH, so the plaintext is never compared against anything
     * stored. An expired or finished conversation is 404 rather than 401: the
     * visitor did nothing wrong, and there is nothing to re-authenticate with.
     */
    private function fromCookie(Request $request): ChatConversation
    {
        $token = $request->cookie(self::COOKIE);

        $conversation = is_string($token) && $token !== ''
            ? ChatConversation::query()->where('token_hash', ChatToken::hash($token))->first()
            : null;

        if ($conversation === null || ! $conversation->acceptsVisitor()) {
            throw ProblemException::make(
                'channels.chat_no_conversation',
                'No open conversation',
                404,
                'This chat has ended or expired. Start a new one to carry on.',
            );
        }

        return $conversation;
    }

    private function withToken(JsonResponse $response, string $token): JsonResponse
    {
        return $response->withCookie(cookie(
            name: self::COOKIE,
            value: $token,
            minutes: max(1, $this->settings->tokenMinutes()),
            /*
             * Pathed to the chat endpoints alone, so it is not attached to
             * every other request this browser makes to the API.
             */
            path: '/api/v1/chat',
            /*
             * Always secure, including in development.
             *
             * `SameSite=None` is REQUIRED here — the widget runs in an iframe
             * on somebody else's site, which is a cross-site context by
             * definition — and browsers reject `None` without `Secure`. Making
             * this conditional on the environment would produce a cookie the
             * browser silently drops in development, which reads as the
             * rejoin feature being broken. Chrome treats `http://localhost` as
             * a secure context, so this works there too.
             */
            secure: true,
            httpOnly: true,
            sameSite: 'none',
        ));
    }
}
