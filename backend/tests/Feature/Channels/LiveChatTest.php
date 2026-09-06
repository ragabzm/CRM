<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Models\User;
use App\Modules\Channels\Adapters\ChatChannelAdapter;
use App\Modules\Channels\Domain\Chat\ChatConversation;
use App\Modules\Channels\Domain\Chat\ChatSettings;
use App\Modules\Channels\Domain\Chat\EmbedOrigins;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A stranger asks a quick question in a chat box.
 *
 * The behaviour that matters is that they end up in exactly the same place a
 * customer who emailed would: one ticket, one customer record, one inbound
 * message row per thing they said, built by the same commands. Chat is a
 * transport, not a second kind of ticket.
 *
 * The other half is what the visitor's token CANNOT do. It resolves to no
 * user, holds no capability, and the only endpoints that accept it are the
 * four the widget calls — so the tests here are as much about what is refused
 * as about what works.
 */
final class LiveChatTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private int $departmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->departmentId = $this->makeDepartment('Support');

        DB::table('channel_accounts')->insert([
            'id' => (string) Str::ulid(),
            'channel' => ChatChannelAdapter::CHANNEL,
            'name' => 'Website chat',
            'is_active' => true,
            'department_id' => $this->departmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    /**
     * The widget's cookie, carried between requests the way a browser would.
     *
     * Laravel's test client does not keep cookies across calls, so without
     * this every request after the first would arrive anonymous — and the
     * tests would prove only that an unauthenticated caller is refused.
     *
     * Sent UNENCRYPTED, which is how the browser actually holds it: the API
     * middleware group carries no cookie encryption, so the widget's token
     * travels as the opaque random string it is. Laravel's test client
     * encrypts `withCookie` values by default, which would hand the controller
     * a blob that hashes to nothing — passing the test only if the lookup were
     * broken in a compensating way.
     */
    private ?string $cookie = null;

    /** Opens a conversation the way the widget does, and keeps the cookie. */
    private function open(array $body = [], ?string $origin = null): \Illuminate\Testing\TestResponse
    {
        $response = $this->withCredentials()
            /*
             * `X-Chat-Embed-Origin`, which is what the widget sends: the
             * request comes from the iframe, whose `Origin` is ours, so the
             * header the browser sets says nothing about the embedding site.
             */
            ->withHeaders($origin === null ? [] : ['X-Chat-Embed-Origin' => $origin])
            ->postJson('/api/v1/chat/conversations', $body);

        $cookie = $response->getCookie('chat_conversation_token', false);

        if ($cookie !== null) {
            $this->cookie = $cookie->getValue();
        }

        return $response;
    }

    /**
     * A request carrying whatever cookie the browser would be holding.
     *
     * `withCredentials`, and it is not a test detail. Laravel's JSON test
     * client omits cookies unless credentials are asked for — which mirrors
     * `fetch` exactly, where a cross-origin call sends none without
     * `credentials: 'include'`. The widget runs in an iframe on somebody
     * else's site, so it is always cross-origin, and forgetting that flag is
     * the defect this helper is shaped to catch rather than hide.
     */
    private function asVisitor(): self
    {
        $this->withCredentials();

        if ($this->cookie !== null) {
            $this->withUnencryptedCookie('chat_conversation_token', $this->cookie);
        }

        return $this;
    }

    private function say(string $body): \Illuminate\Testing\TestResponse
    {
        return $this->asVisitor()
            ->postJson('/api/v1/chat/conversations/current/messages', ['body' => $body]);
    }

    public function test_a_visitor_opens_a_conversation_without_an_account(): void
    {
        $response = $this->open(['visitor_name' => 'Hana']);

        $response->assertCreated();
        $response->assertJsonPath('data.state', 'waiting');

        // No ticket yet. A box somebody opened and closed again is not a
        // request, and one in the queue would be silence an agent has to read.
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_the_first_message_creates_a_ticket_on_the_chat_channel(): void
    {
        $this->open(['visitor_name' => 'Hana']);

        $this->say('My invoice is wrong')->assertOk();

        $ticket = Ticket::query()->sole();

        $this->assertSame('chat', $ticket->channel->value);
        // The subject is the first thing they said. A chat has no subject line,
        // and a fixed "Live chat" makes every row in the queue identical.
        $this->assertSame('My invoice is wrong', $ticket->subject);
        $this->assertSame($this->departmentId, (int) $ticket->department_id);
    }

    public function test_every_message_lands_on_the_same_ticket(): void
    {
        $this->open();

        $this->say('First');
        $this->say('Second');
        $this->say('Third');

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(3, DB::table('ticket_messages')->where('direction', 'inbound')->count());
    }

    public function test_the_transcript_is_ordinary_ticket_messages_not_a_blob(): void
    {
        $this->open();
        $this->say('Hello');

        /*
         * The whole point of the constraint: the history stays uniform and the
         * conversation is searchable like any other. A `transcript` column
         * would be a second kind of ticket history that no screen, search or
         * export knows about.
         */
        $columns = array_keys((array) DB::table('chat_conversations')->first());

        foreach (['transcript', 'messages', 'body', 'log'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }

        $this->assertSame('Hello', DB::table('ticket_messages')->value('body'));
    }

    public function test_a_visitor_who_types_a_ticket_reference_cannot_move_their_chat(): void
    {
        $someoneElse = $this->makeTicket();

        $this->open();
        $this->say('Hello');

        $mine = (string) ChatConversation::query()->value('ticket_id');

        // A subject token would normally correlate. Chat knows its own ticket,
        // so the heuristics are never consulted.
        $this->say('About '.$someoneElse->reference.' please');

        $this->assertSame(
            2,
            DB::table('ticket_messages')->where('ticket_id', $mine)->count(),
        );
        $this->assertSame(
            'known_conversation',
            DB::table('inbound_messages')->orderByDesc('id')->value('correlation_reason'),
        );
    }

    public function test_a_visitor_who_gives_an_email_joins_the_record_they_already_had(): void
    {
        $existing = $this->makeCustomer();

        DB::table('contact_identifiers')->insert([
            'id' => (string) Str::ulid(),
            'customer_id' => $existing,
            'kind' => 'email',
            'value' => 'hana@example.test',
            'value_normalised' => 'hana@example.test',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->open(['visitor_identifier' => 'hana@example.test']);
        $this->say('It is me again');

        // One customer, not two. Somebody who emailed on Monday and chatted on
        // Tuesday is one person.
        $this->assertSame($existing, (string) Ticket::query()->value('customer_id'));
    }

    public function test_a_visitor_who_gives_nothing_still_becomes_a_customer(): void
    {
        $this->open();
        $this->say('Just a quick question');

        $ticket = Ticket::query()->sole();

        $this->assertNotNull($ticket->customer_id);
        // The session is the only handle we have on them, so it is the
        // identifier — and the enum says out loud what that costs.
        $this->assertSame('chat', DB::table('contact_identifiers')->value('kind'));
    }

    public function test_the_widget_can_read_back_what_was_said(): void
    {
        $this->open();
        $this->say('Hello');

        $response = $this->asVisitor()->getJson('/api/v1/chat/conversations/current/messages');

        $response->assertOk();
        $response->assertJsonPath('data.messages.0.from', 'visitor');
        $response->assertJsonPath('data.messages.0.body', 'Hello');
        // Stated in the response rather than hidden: this is a polling widget
        // and the interval is the accepted trade.
        $this->assertIsInt($response->json('data.poll_seconds'));
    }

    public function test_an_agent_reply_reaches_the_widget(): void
    {
        $this->open();
        $this->say('Hello');

        $agent = $this->makeUser(Roles::AGENT);

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), (string) $agent->name),
            (string) Ticket::query()->value('id'),
            MessageDirection::Outbound,
            'Looking now.',
        );

        $messages = $this->asVisitor()->getJson('/api/v1/chat/conversations/current/messages')->json('data.messages');

        $this->assertCount(2, $messages);
        $this->assertSame('agent', $messages[1]['from']);
        $this->assertSame('Looking now.', $messages[1]['body']);
    }

    public function test_an_internal_note_never_reaches_the_widget(): void
    {
        $this->open();
        $this->say('Hello');

        $agent = $this->makeUser(Roles::AGENT);

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), (string) $agent->name),
            (string) Ticket::query()->value('id'),
            MessageDirection::Internal,
            'This one is a time-waster.',
        );

        $body = $this->asVisitor()->getJson('/api/v1/chat/conversations/current/messages')->getContent();

        /*
         * A colleague's private remark about the visitor is the one thing on a
         * ticket that must never cross this boundary. Filtered at the QUERY,
         * so a field added later cannot leak it.
         */
        $this->assertStringNotContainsString('time-waster', $body);
    }

    public function test_a_reload_inside_the_tokens_life_rejoins_the_same_conversation(): void
    {
        $this->open();
        $this->say('Hello');

        $id = (string) ChatConversation::query()->value('id');

        // The widget reloads. Its memory is gone; the http-only cookie is not.
        $response = $this->asVisitor()->getJson('/api/v1/chat/conversations/current');

        $response->assertOk();
        $response->assertJsonPath('data.id', $id);
    }

    public function test_a_visitor_returning_after_expiry_starts_a_new_one(): void
    {
        $this->settings()->set(ChatSettings::TOKEN_MINUTES, 5, null);

        $this->open();
        $this->say('Hello');

        $first = (string) ChatConversation::query()->value('ticket_id');
        $this->assertNotSame('', $first);

        $this->travel(10)->minutes();

        // Not an error — "you have no conversation" is the ordinary case for
        // anybody opening the widget.
        $this->asVisitor()->getJson('/api/v1/chat/conversations/current')->assertNotFound();

        // And the previous conversation is already a ticket.
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_a_token_reaches_nothing_outside_the_chat_surface(): void
    {
        $this->open();
        $this->say('Hello');

        $ticketId = (string) Ticket::query()->value('id');

        /*
         * The token resolves to no user and holds no capability. These are the
         * endpoints somebody would try first with a stolen one.
         */
        $this->getJson('/api/v1/tickets/'.$ticketId)->assertUnauthorized();
        $this->getJson('/api/v1/tickets')->assertUnauthorized();
        $this->getJson('/api/v1/chat-desk/conversations')->assertUnauthorized();
    }

    public function test_an_unlisted_origin_gets_no_token_and_no_conversation(): void
    {
        $response = $this->open([], 'https://not-our-site.example');

        $response->assertForbidden();
        $this->assertSame('channels.chat_origin_refused', $response->json('code'));

        // Not a token that fails later, and not an empty widget: nothing was
        // created at all, so reloading cannot fill the table.
        $this->assertSame(0, ChatConversation::query()->count());
    }

    public function test_the_allowed_origins_are_readable_for_the_frame_policy(): void
    {
        $this->settings()->set(EmbedOrigins::SETTING, ['https://partner.example'], null);

        $response = $this->getJson('/api/v1/chat/embed-origins');

        $response->assertOk();

        /*
         * Read by the frontend to build `frame-ancestors` on the frame
         * document — the half of the embedding rule a browser enforces, and
         * the reason the claimed origin above only has to stop a casual
         * copy-paste.
         */
        $this->assertContains('https://partner.example', $response->json('data.origins'));
        // Our own origins, always, so chat works on the portal out of the box.
        $this->assertContains(rtrim((string) config('app.frontend_url'), '/'), $response->json('data.origins'));
    }

    public function test_a_listed_origin_is_allowed(): void
    {
        $this->settings()->set(EmbedOrigins::SETTING, ['https://partner.example'], null);

        $this->open([], 'https://partner.example')->assertCreated();
    }

    public function test_a_disabled_channel_says_so_rather_than_failing_silently(): void
    {
        DB::table('channel_accounts')
            ->where('channel', ChatChannelAdapter::CHANNEL)
            ->update(['is_active' => false]);

        $response = $this->open();

        $response->assertStatus(503);
        $this->assertSame('channels.chat_closed', $response->json('code'));
        // Told what to do instead. An empty box that swallows what somebody
        // types is worse than no box.
        $this->assertNotEmpty($response->json('detail'));
    }

    public function test_two_agents_clicking_at_once_resolve_to_one_winner(): void
    {
        $this->open();
        $this->say('Hello');

        $id = (string) ChatConversation::query()->value('id');

        $first = $this->makeUser(Roles::AGENT);
        $second = $this->makeUser(Roles::AGENT);

        $this->actingAs($first, 'web')->withIdempotencyKey()
            ->postJson('/api/v1/chat-desk/conversations/'.$id.'/take')
            ->assertOk();

        $loser = $this->actingAs($second, 'web')->withIdempotencyKey()
            ->postJson('/api/v1/chat-desk/conversations/'.$id.'/take');

        /*
         * Refused with a stated reason rather than silently overwritten. A
         * refusal saying only "could not take" would send them back to the
         * list to try again on a row that is gone.
         */
        $loser->assertStatus(409);
        $this->assertSame('channels.chat_already_taken', $loser->json('code'));
        $this->assertStringContainsString((string) $first->name, (string) $loser->json('detail'));

        $this->assertSame((int) $first->getKey(), (int) ChatConversation::query()->value('taken_by'));
    }

    public function test_taking_it_twice_yourself_is_not_a_refusal(): void
    {
        $this->open();
        $this->say('Hello');

        $id = (string) ChatConversation::query()->value('id');
        $agent = $this->makeUser(Roles::AGENT);

        $this->actingAs($agent, 'web')->withIdempotencyKey()
            ->postJson('/api/v1/chat-desk/conversations/'.$id.'/take')->assertOk();

        // A double click, or a retried request. Nothing is wrong.
        $this->actingAs($agent, 'web')->withIdempotencyKey()
            ->postJson('/api/v1/chat-desk/conversations/'.$id.'/take')->assertOk();
    }

    public function test_a_taken_conversation_leaves_everybody_elses_waiting_list(): void
    {
        $this->open();
        $this->say('Hello');

        $id = (string) ChatConversation::query()->value('id');
        $first = $this->makeUser(Roles::AGENT);
        $second = $this->makeUser(Roles::AGENT);

        $this->actingAs($first, 'web')->withIdempotencyKey()
            ->postJson('/api/v1/chat-desk/conversations/'.$id.'/take');

        $list = $this->actingAs($second, 'web')->getJson('/api/v1/chat-desk/conversations');

        $this->assertSame([], $list->json('data.waiting'));
        $this->assertSame([], $list->json('data.mine'));

        // And it is on the taker's own list.
        $mine = $this->actingAs($first, 'web')->getJson('/api/v1/chat-desk/conversations');
        $this->assertCount(1, $mine->json('data.mine'));
    }

    public function test_the_waiting_list_holds_no_position_priority_or_routing(): void
    {
        $this->open();
        $this->say('Hello');

        $agent = $this->makeUser(Roles::AGENT);
        $row = $this->actingAs($agent, 'web')->getJson('/api/v1/chat-desk/conversations')->json('data.waiting.0');

        /*
         * A LIST, not a queue. Every one of these is a field that needs an
         * availability state to mean anything, and availability is what this
         * story rules out.
         */
        foreach (['position', 'priority', 'assigned_to', 'routing', 'skill'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $row);
        }
    }

    public function test_a_conversation_nobody_has_spoken_in_is_not_on_the_waiting_list(): void
    {
        // Opened and left alone. Not a person waiting for an answer.
        $this->open();

        $agent = $this->makeUser(Roles::AGENT);

        $this->assertSame(
            [],
            $this->actingAs($agent, 'web')->getJson('/api/v1/chat-desk/conversations')->json('data.waiting'),
        );
    }

    public function test_an_agent_without_the_capability_cannot_see_who_is_waiting(): void
    {
        $this->open();
        $this->say('Hello');

        $customer = $this->makeUser(Roles::CUSTOMER);

        $this->actingAs($customer, 'web')
            ->getJson('/api/v1/chat-desk/conversations')
            ->assertForbidden();
    }

    public function test_closing_a_conversation_leaves_the_ticket_alone(): void
    {
        $this->open();
        $this->say('Hello');

        $before = Ticket::query()->sole()->only(['status', 'version']);

        $this->asVisitor()->postJson('/api/v1/chat/conversations/current/close')->assertOk();

        /*
         * A finished chat is not a resolved request — the agent may have
         * promised to look into something — and closing the ticket here would
         * take that decision away from the person who has to make it.
         */
        $this->assertEquals($before, Ticket::query()->sole()->only(['status', 'version']));
        $this->assertSame('ended', ChatConversation::query()->value('ended_at') === null ? 'open' : 'ended');
    }

    public function test_the_widget_endpoints_are_exempt_from_csrf(): void
    {
        /*
         * Asserted against the CONFIGURATION, which is the only place it can
         * be seen from here.
         *
         * `PreventRequestForgery` short-circuits when `runningUnitTests()`, so
         * no test in this suite can exercise CSRF at all — which is exactly
         * how the widget shipped returning 419 to every message in a real
         * browser while all of these passed. The exemption is deliberate: the
         * widget runs cross-site in an iframe and cannot hold a CSRF token
         * anywhere JavaScript can reach.
         *
         * If this ever fails, read the note in `bootstrap/app.php` before
         * deleting it — what it costs is argued there.
         */
        $neverVerify = (new \ReflectionClass(
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ))->getStaticPropertyValue('neverVerify', []);

        $this->assertContains('api/v1/chat/*', $neverVerify);
    }

    public function test_a_closed_conversation_refuses_the_visitors_next_message(): void
    {
        $this->open();
        $this->say('Hello');
        $this->asVisitor()->postJson('/api/v1/chat/conversations/current/close');

        // The token was scoped to this conversation and it is over.
        $this->say('Are you there?')->assertNotFound();
    }
}
