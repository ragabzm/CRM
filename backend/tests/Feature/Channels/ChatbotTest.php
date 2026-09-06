<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Modules\Ai\Contracts\AiCapability;
use App\Modules\Ai\Contracts\AiTransport;
use App\Modules\Ai\Contracts\SanitisedPrompt;
use App\Modules\Ai\Domain\GuardedAiProvider;
use App\Modules\Ai\Domain\TransmissionMode;
use App\Modules\Channels\Adapters\ChatChannelAdapter;
use App\Modules\Channels\Domain\Chat\ChatConversation;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * An immediate answer from the help centre, and a person the moment it is not
 * good enough.
 *
 * This is the ONE place in the product where a machine reaches a customer with
 * nobody having read what it wrote — so every test here leans the same way the
 * code does. When in doubt, hand off: an unnecessary handoff costs an agent a
 * minute, and a confident wrong answer costs the customer, who will not come
 * back to tell us it was wrong.
 */
final class ChatbotTest extends TestCase
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

    private function enableChatbot(): void
    {
        $this->settings()->set(GuardedAiProvider::TRANSMISSION, TransmissionMode::Redacted->value, null);
        $this->settings()->set(AiCapability::Chatbot->setting(), true, null);
    }

    /** A transport that answers whatever it is told to. */
    private function answering(?string $answer): void
    {
        $this->app->instance(AiTransport::class, new class($answer) implements AiTransport
        {
            /** @var list<string> */
            public array $sent = [];

            public function __construct(private readonly ?string $answer) {}

            public function complete(SanitisedPrompt $prompt, int $timeoutSeconds): ?string
            {
                $this->sent[] = $prompt->text;

                return $this->answer;
            }

            public function name(): string
            {
                return 'answering';
            }
        });
    }

    private function open(): void
    {
        $response = $this->withCredentials()->postJson('/api/v1/chat/conversations', []);
        $this->cookie = (string) $response->getCookie('chat_conversation_token', false)?->getValue();
    }

    private function say(string $body): \Illuminate\Testing\TestResponse
    {
        return $this->withCredentials()
            ->withUnencryptedCookie('chat_conversation_token', (string) $this->cookie)
            ->postJson('/api/v1/chat/conversations/current/messages', ['body' => $body]);
    }

    private function transcript(): array
    {
        return $this->withCredentials()
            ->withUnencryptedCookie('chat_conversation_token', (string) $this->cookie)
            ->getJson('/api/v1/chat/conversations/current/messages')
            ->json('data.messages');
    }

    private function article(string $title, string $body, bool $internal = false, string $status = 'published'): string
    {
        $id = (string) Str::ulid();

        $category = (int) DB::table('article_categories')->insertGetId([
            'name_en' => 'Billing', 'name_ar' => 'الفوترة', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('articles')->insert([
            'id' => $id,
            'category_id' => $category,
            'type' => 'faq',
            'status' => $status,
            'internal_only' => $internal,
            'default_locale' => 'en',
            'has_been_published' => $status === 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('article_translations')->insert([
            'id' => (string) Str::ulid(),
            'article_id' => $id,
            'locale' => 'en',
            'title' => $title,
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_it_answers_from_a_published_article_and_cites_it(): void
    {
        $article = $this->article('Refunding a duplicate charge', 'A duplicate charge is refunded within three working days.');

        $this->enableChatbot();
        $this->answering('A duplicate charge is refunded within three working days.');

        $this->open();
        $this->say('I have a duplicate charge on my card');

        $messages = $this->transcript();
        $answer = end($messages);

        $this->assertSame('assistant', $answer['from']);
        $this->assertStringContainsString('three working days', $answer['body']);

        /*
         * The stable id-keyed URL, so a cited article that is later retitled
         * still resolves from an old transcript. A title pasted into the text
         * would rot the moment somebody edited it.
         */
        $this->assertStringContainsString('/portal/help/'.$article, $answer['body']);
    }

    public function test_it_never_presents_itself_as_a_person(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three working days.');

        $this->enableChatbot();
        $this->answering('Refunded within three days.');

        $this->open();
        $this->say('duplicate charge please help');

        $messages = $this->transcript();
        $answer = end($messages);

        $this->assertSame('assistant', $answer['from']);
        // Never a colleague's name, and never blank — a message from nobody
        // reads as a message from the desk.
        $this->assertNotSame('agent', $answer['from']);

        /*
         * And the label persists in the transcript that becomes the ticket: a
         * colleague picking this up must be able to see which turns were the
         * machine's.
         */
        $stored = DB::table('ticket_messages')
            ->where('direction', 'outbound')
            ->orderByDesc('id')
            ->first(['author_type', 'author_name']);

        $this->assertSame('system', $stored->author_type);
        $this->assertSame(Actor::CHATBOT, $stored->author_name);
    }

    public function test_an_internal_article_never_reaches_a_customer(): void
    {
        $this->article('How we handle duplicate charges internally', 'Never tell the customer about the refund float.', internal: true);

        $this->enableChatbot();
        $this->answering('Never tell the customer about the refund float.');

        $this->open();
        $this->say('duplicate charge on my account');

        $messages = $this->transcript();
        $body = implode("\n", array_column($messages, 'body'));

        /*
         * Excluded at QUERY level, not filtered afterwards. With no public
         * article to cite there is nothing to answer from, so the only correct
         * outcome is a handoff — and the internal text never entered a prompt
         * either.
         */
        $this->assertStringNotContainsString('refund float', $body);
        $this->assertSame('handed_off', $this->state());
    }

    public function test_a_draft_article_is_never_answered_from(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.', status: 'draft');

        $this->enableChatbot();
        $this->answering('Refunded within three days.');

        $this->open();
        $this->say('duplicate charge on my account');

        $this->assertSame('handed_off', $this->state());
    }

    public function test_no_citable_article_means_a_person(): void
    {
        $this->enableChatbot();
        $this->answering('I could make something up here.');

        $this->open();
        $this->say('Something nothing has been written about');

        /*
         * An answer with nothing behind it is an answer this product cannot
         * stand behind — and the transport was never even asked.
         */
        $this->assertSame('handed_off', $this->state());
        $this->assertSame([], $this->app->make(AiTransport::class)->sent);
    }

    public function test_asking_for_a_person_gets_a_person(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.');

        $this->enableChatbot();
        $this->answering('Refunded within three days.');

        $this->open();
        $this->say('I want to talk to a human about this duplicate charge');

        // The one request a customer has made explicitly. There is no intent
        // model here to be wrong about it.
        $this->assertSame('handed_off', $this->state());
    }

    public function test_asking_for_a_person_in_arabic_gets_a_person(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.');

        $this->enableChatbot();
        $this->answering('Refunded within three days.');

        $this->open();
        $this->say('عايز اكلم موظف من فضلك');

        $this->assertSame('handed_off', $this->state());
    }

    public function test_a_provider_failure_is_a_handoff_and_says_nothing_about_the_provider(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.');

        $this->enableChatbot();

        $this->app->instance(AiTransport::class, new class implements AiTransport
        {
            public function complete(SanitisedPrompt $prompt, int $timeoutSeconds): ?string
            {
                throw new \RuntimeException('the provider fell over');
            }

            public function name(): string
            {
                return 'exploding';
            }
        });

        $this->open();
        $this->say('duplicate charge on my account');

        $this->assertSame('handed_off', $this->state());

        $body = implode("\n", array_column($this->transcript(), 'body'));

        /*
         * Told a person is coming, and nothing else. Not an error, not a
         * retry, not a stack of apologies — and not a word about a provider
         * the customer has never heard of and can do nothing about.
         */
        $this->assertStringContainsString(__('channels.chat.handing_off', [], 'en'), $body);
        $this->assertStringNotContainsString('provider', mb_strtolower($body));
        $this->assertStringNotContainsString('error', mb_strtolower($body));
    }

    public function test_a_silent_provider_is_a_handoff(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.');

        $this->enableChatbot();
        // The port's degraded return: unreachable, timed out, or transmission
        // disabled all arrive here as null.
        $this->answering(null);

        $this->open();
        $this->say('duplicate charge on my account');

        $this->assertSame('handed_off', $this->state());
    }

    public function test_the_capability_off_offers_a_person_with_no_mention_of_a_switch(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.');
        // Chatbot deliberately left off.

        $this->open();
        $this->say('duplicate charge on my account');

        $this->assertSame('handed_off', $this->state());

        $body = mb_strtolower(implode("\n", array_column($this->transcript(), 'body')));

        // A customer told "the assistant is unavailable" has been given a fact
        // about our configuration and nothing they can use.
        $this->assertStringNotContainsString('unavailable', $body);
        $this->assertStringNotContainsString('disabled', $body);
        $this->assertStringNotContainsString('assistant', $body);
    }

    public function test_a_conversation_the_bot_is_answering_is_not_on_the_waiting_list(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.');

        $this->enableChatbot();
        $this->answering('Refunded within three days.');

        $this->open();
        $this->say('duplicate charge on my account');

        $agent = $this->makeUser(Roles::AGENT);

        /*
         * Nobody is waiting: the bot answered. Putting it on the list would
         * have agents opening chats that were already dealt with, which is the
         * fastest way to make a waiting list nobody trusts.
         */
        $this->assertSame(
            [],
            $this->actingAs($agent, 'web')->getJson('/api/v1/chat-desk/conversations')->json('data.waiting'),
        );
    }

    public function test_a_handed_off_conversation_joins_the_waiting_list_with_everything_said(): void
    {
        $this->enableChatbot();
        $this->answering(null);

        $this->open();
        $this->say('I have a problem nobody has written about');

        $agent = $this->makeUser(Roles::AGENT);

        $waiting = $this->actingAs($agent, 'web')
            ->getJson('/api/v1/chat-desk/conversations')
            ->json('data.waiting');

        $this->assertCount(1, $waiting);

        /*
         * Carrying everything already said, so the customer never repeats
         * themselves. The transcript is the ticket's own messages — there was
         * never a second copy to carry across.
         */
        $ticketId = $waiting[0]['ticket_id'];

        $this->assertSame(
            'I have a problem nobody has written about',
            DB::table('ticket_messages')->where('ticket_id', $ticketId)->where('direction', 'inbound')->value('body'),
        );
    }

    public function test_the_bot_does_not_talk_over_an_agent_who_has_taken_it(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.');

        $this->enableChatbot();
        $this->answering(null);

        $this->open();
        $this->say('duplicate charge on my account');

        $agent = $this->makeUser(Roles::AGENT);
        $id = (string) ChatConversation::query()->value('id');

        $this->actingAs($agent, 'web')->withIdempotencyKey()
            ->postJson('/api/v1/chat-desk/conversations/'.$id.'/take')->assertOk();

        $this->answering('The bot would like to speak.');
        $before = count($this->transcript());

        $this->say('Are you there?');

        // One new message — the visitor's. From the customer's point of view
        // somebody arrived, and a machine chiming in reads as being passed
        // back.
        $this->assertSame($before + 1, count($this->transcript()));
    }

    public function test_the_chatbot_changes_nothing_about_the_ticket(): void
    {
        $this->article('Refunding a duplicate charge', 'Refunded within three days.');

        $this->enableChatbot();
        $this->answering('Refunded within three days.');

        $this->open();
        $this->say('duplicate charge on my account');

        $ticket = DB::table('tickets')->first();

        /*
         * It never changes a status, a priority, a category or an assignee,
         * never sends an email, and never resolves or closes anything. The
         * only thing it writes is a message.
         */
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->assignee_id);
        $this->assertNull($ticket->category_id);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_a_visitor_who_leaves_still_leaves_the_whole_transcript(): void
    {
        $this->enableChatbot();
        $this->answering(null);

        $this->open();
        $this->say('I have a problem nobody has written about');

        $ticketId = (string) ChatConversation::query()->value('ticket_id');

        // They close the tab. The ticket and its transcript are already there.
        $this->assertNotSame('', $ticketId);
        $this->assertSame(
            2,
            DB::table('ticket_messages')->where('ticket_id', $ticketId)->count(),
        );
    }

    /** `waiting` while the bot has it, `handed_off` once it has given up. */
    private function state(): string
    {
        return ChatConversation::query()->value('handed_off_at') === null ? 'waiting' : 'handed_off';
    }
}
