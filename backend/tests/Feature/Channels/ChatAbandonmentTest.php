<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Modules\Channels\Adapters\ChatChannelAdapter;
use App\Modules\Channels\Domain\Chat\AbandonStaleConversations;
use App\Modules\Channels\Domain\Chat\ChatConversation;
use App\Modules\Channels\Domain\Chat\ChatSettings;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The visitor closed the tab.
 *
 * Most chats end this way — somebody gets their answer, or gives up waiting,
 * and simply leaves. Nobody presses a button. Without the sweep the
 * conversation sits in the waiting list for ever and an agent keeps trying to
 * answer an empty tab.
 *
 * The rule that carries the whole thing is IDEMPOTENCE: running it twice
 * produces nothing new. A sweep that appended a note and then marked the row
 * would, on a crash between the two, add "the visitor left" to the same ticket
 * every minute for ever.
 */
final class ChatAbandonmentTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private ?string $cookie = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        DB::table('channel_accounts')->insert([
            'id' => (string) Str::ulid(),
            'channel' => ChatChannelAdapter::CHANNEL,
            'name' => 'Website chat',
            'is_active' => true,
            'department_id' => $this->makeDepartment('Support'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    private function conversationThatSaidSomething(): ChatConversation
    {
        $response = $this->withCredentials()->postJson('/api/v1/chat/conversations', []);
        $this->cookie = (string) $response->getCookie('chat_conversation_token', false)?->getValue();

        $this->withCredentials()
            ->withUnencryptedCookie('chat_conversation_token', $this->cookie)
            ->postJson('/api/v1/chat/conversations/current/messages', ['body' => 'Anyone there?']);

        return ChatConversation::query()->sole();
    }

    private function sweep(): int
    {
        return $this->app->make(AbandonStaleConversations::class)->run();
    }

    public function test_a_silent_conversation_is_given_up_on(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $conversation = $this->conversationThatSaidSomething();

        $this->travel(11)->minutes();

        $this->assertSame(1, $this->sweep());
        $this->assertSame('abandoned', $conversation->refresh()->state());
    }

    public function test_the_ticket_already_holds_the_whole_transcript(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $conversation = $this->conversationThatSaidSomething();
        $this->travel(11)->minutes();
        $this->sweep();

        $ticketId = (string) $conversation->refresh()->ticket_id;

        /*
         * Nothing is assembled at the end. The ticket was created by the first
         * message and every message since is an ordinary `ticket_messages`
         * row, so an abandoned conversation is a complete one that nobody
         * closed.
         */
        $this->assertSame(1, Ticket::query()->where('id', $ticketId)->count());
        $this->assertSame(
            'Anyone there?',
            DB::table('ticket_messages')->where('ticket_id', $ticketId)->where('direction', 'inbound')->value('body'),
        );
    }

    public function test_it_leaves_an_internal_note_saying_what_happened(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $conversation = $this->conversationThatSaidSomething();
        $this->travel(11)->minutes();
        $this->sweep();

        $note = DB::table('ticket_messages')
            ->where('ticket_id', $conversation->refresh()->ticket_id)
            ->where('direction', 'internal')
            ->first();

        $this->assertNotNull($note);
        /*
         * INTERNAL. It is a fact about the channel for whoever picks the
         * ticket up — telling somebody who left that they left would be a
         * message to an empty room.
         */
        $this->assertSame('internal', $note->direction);
    }

    public function test_running_it_again_produces_nothing_new(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $conversation = $this->conversationThatSaidSomething();
        $this->travel(11)->minutes();

        $this->assertSame(1, $this->sweep());

        $notesAfterFirst = DB::table('ticket_messages')->where('direction', 'internal')->count();

        // A slow minute, two schedulers, a retried job.
        $this->assertSame(0, $this->sweep());
        $this->assertSame(0, $this->sweep());

        $this->assertSame($notesAfterFirst, DB::table('ticket_messages')->where('direction', 'internal')->count());
        $this->assertSame('abandoned', $conversation->refresh()->state());
    }

    public function test_the_console_command_is_the_same_sweep(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $this->conversationThatSaidSomething();
        $this->travel(11)->minutes();

        $this->artisan('chat:sweep')->assertSuccessful();
        $this->artisan('chat:sweep')->assertSuccessful();

        $this->assertSame(1, DB::table('ticket_messages')->where('direction', 'internal')->count());
    }

    public function test_a_live_conversation_is_left_alone(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $conversation = $this->conversationThatSaidSomething();

        $this->travel(3)->minutes();

        $this->assertSame(0, $this->sweep());
        $this->assertSame('waiting', $conversation->refresh()->state());
    }

    public function test_a_conversation_somebody_closed_is_not_abandoned_as_well(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $conversation = $this->conversationThatSaidSomething();

        $this->withCredentials()
            ->withUnencryptedCookie('chat_conversation_token', (string) $this->cookie)
            ->postJson('/api/v1/chat/conversations/current/close');

        $this->travel(11)->minutes();
        $this->sweep();

        /*
         * Ended and abandoned are different answers to "what happened here?",
         * and a row claiming both would make "how many did we actually
         * answer?" a question with two answers.
         */
        $this->assertSame('ended', $conversation->refresh()->state());
        $this->assertNull($conversation->abandoned_at);
    }

    public function test_a_widget_opened_and_never_typed_in_makes_no_ticket(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $this->withCredentials()->postJson('/api/v1/chat/conversations', []);

        $this->travel(11)->minutes();

        $this->assertSame(1, $this->sweep());
        // There was no request. Creating one would put silence in the queue.
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame('abandoned', ChatConversation::query()->sole()->state());
    }

    public function test_activity_from_either_side_keeps_it_alive(): void
    {
        $this->settings()->set(ChatSettings::ABANDON_AFTER_MINUTES, 10, null);

        $conversation = $this->conversationThatSaidSomething();

        $this->travel(8)->minutes();

        $this->withCredentials()
            ->withUnencryptedCookie('chat_conversation_token', (string) $this->cookie)
            ->postJson('/api/v1/chat/conversations/current/messages', ['body' => 'Still here']);

        $this->travel(8)->minutes();

        // Sixteen minutes since the first message, eight since the last. The
        // window is about silence, not about age.
        $this->assertSame(0, $this->sweep());
        $this->assertSame('waiting', $conversation->refresh()->state());
    }
}
