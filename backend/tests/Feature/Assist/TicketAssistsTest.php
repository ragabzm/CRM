<?php

declare(strict_types=1);

namespace Tests\Feature\Assist;

use App\Models\User;
use App\Modules\Ai\Contracts\AiCapability;
use App\Modules\Ai\Contracts\AiTransport;
use App\Modules\Ai\Contracts\SanitisedPrompt;
use App\Modules\Ai\Domain\GuardedAiProvider;
use App\Modules\Ai\Domain\TransmissionMode;
use App\Modules\Ai\Infrastructure\NullAiTransport;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The machine drafts, summarises and suggests. The agent decides.
 *
 * That sentence is the whole epic and most of these tests are about its second
 * half: nothing is applied, nothing is sent, nothing is stored, and every
 * artefact arrives labelled. The tests worth the most are the ones asserting
 * a code path does NOT exist.
 */
final class TicketAssistsTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private User $agent;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->agent = $this->makeUser(Roles::AGENT);
        $this->ticket = $this->makeTicket([
            'subject' => 'The invoice is wrong',
            'description' => 'I was charged twice and want a refund for the duplicate charge.',
            'assignee_id' => $this->agent->getKey(),
        ]);

        $this->app->instance(AiTransport::class, $this->app->make(NullAiTransport::class));
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    /** Turns AI on so the tests below are about what still refuses. */
    private function enable(AiCapability ...$capabilities): void
    {
        $this->settings()->set(GuardedAiProvider::TRANSMISSION, TransmissionMode::Redacted->value, null);

        foreach ($capabilities as $capability) {
            $this->settings()->set($capability->setting(), true, null);
        }
    }

    /** A transport that answers, so the happy paths have something to shape. */
    private function answering(string $answer): void
    {
        $this->app->instance(AiTransport::class, new class($answer) implements AiTransport
        {
            /** @var list<string> */
            public array $sent = [];

            public function __construct(private readonly string $answer) {}

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

    private function assist(string $what): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->agent, 'web')
            ->getJson("/api/v1/tickets/{$this->ticket->getKey()}/assist/{$what}");
    }

    private function say(string $body, MessageDirection $direction = MessageDirection::Inbound): void
    {
        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $this->agent->getKey(), (string) $this->agent->name),
            (string) $this->ticket->getKey(),
            $direction,
            $body,
        );
    }

    public function test_a_summary_is_generated_only_when_an_agent_asks(): void
    {
        $this->enable(AiCapability::Summary);
        $this->answering('They were charged twice and want a refund.');

        $this->say('I was charged twice.');

        /*
         * Nothing has been generated yet. There is no message-count
         * threshold, no automatic generation and no setting that would create
         * one — a summary that appeared on its own would be a request to a
         * provider nobody asked for, on every ticket, for ever.
         */
        $this->assertSame([], $this->app->make(AiTransport::class)->sent);

        $response = $this->assist('summary');

        $response->assertOk();
        $this->assertSame('They were charged twice and want a refund.', $response->json('data.summary'));
    }

    public function test_a_summary_is_never_written_into_the_conversation_or_the_history(): void
    {
        $this->enable(AiCapability::Summary);
        $this->answering('A summary.');
        $this->say('I was charged twice.');

        $messagesBefore = DB::table('ticket_messages')->count();
        $eventsBefore = DB::table('ticket_events')->count();

        $this->assist('summary')->assertOk();

        /*
         * Not stored anywhere. Computed per request, so a stale summary of a
         * conversation that has moved on cannot exist — and regenerating is a
         * fresh call rather than a cache invalidation, because there is no
         * cache to invalidate.
         */
        $this->assertSame($messagesBefore, DB::table('ticket_messages')->count());
        $this->assertSame($eventsBefore, DB::table('ticket_events')->count());

        $columns = array_keys((array) DB::table('tickets')->first());
        $this->assertNotContains('ai_summary', $columns);
        $this->assertNotContains('summary', $columns);
    }

    public function test_a_summary_never_carries_an_internal_note(): void
    {
        $this->enable(AiCapability::Summary);
        $this->answering('A summary.');

        $this->say('I was charged twice.');
        $this->say('This one is a time-waster.', MessageDirection::Internal);

        $this->assist('summary')->assertOk();

        /*
         * A colleague's private remark about the customer is the one thing on
         * a ticket that must not leave the building. Filtered at the QUERY, so
         * it cannot arrive in a prompt because somebody later added a field.
         */
        $sent = implode("\n", $this->app->make(AiTransport::class)->sent);

        $this->assertStringNotContainsString('time-waster', $sent);
        $this->assertStringContainsString('charged twice', $sent);
    }

    public function test_a_suggested_reply_is_offered_and_never_sent(): void
    {
        $this->enable(AiCapability::SuggestedReply);
        $this->answering("Sorry about that — I have refunded it.\n---\nI can see the duplicate charge.");
        $this->say('I was charged twice.');

        $before = DB::table('ticket_messages')->count();

        $response = $this->assist('reply');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.suggestions'));

        /*
         * Offered, not sent. Nothing reached the customer, and there is no
         * endpoint that would — the agent sends from a composer they edited.
         */
        $this->assertSame($before, DB::table('ticket_messages')->count());
    }

    public function test_no_message_is_ever_authored_by_the_ai(): void
    {
        $this->enable(AiCapability::SuggestedReply);
        $this->answering('A draft.');
        $this->say('I was charged twice.');

        $this->assist('reply')->assertOk();

        $authors = DB::table('ticket_messages')->pluck('author_type')->unique()->all();

        /*
         * The assertion the story asks for by name. No command sends a
         * customer-facing message with an AI actor, because there is no AI
         * actor: the four types are staff, portal, customer and system, and
         * nothing in this module holds a command that could use any of them.
         */
        foreach ($authors as $author) {
            $this->assertNotSame('ai', $author);
            $this->assertNotSame('assistant', $author);
        }
    }

    public function test_a_category_is_proposed_beside_the_field_and_not_applied(): void
    {
        $billing = (int) DB::table('ticket_categories')->insertGetId([
            'name_en' => 'Billing', 'name_ar' => 'الفوترة', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->enable(AiCapability::CategoryProposal);
        $this->answering((string) $billing);

        $response = $this->assist('category');

        $response->assertOk();
        $response->assertJsonPath('data.proposal.category_id', $billing);
        $response->assertJsonPath('data.proposal.name', 'Billing');

        /*
         * The ticket is untouched. A pre-filled field is an application, and
         * applications are what this story refuses — so the proposal is a
         * value beside the field and the ticket's own category is still null.
         */
        $this->assertNull($this->ticket->refresh()->category_id);
        $this->assertSame(0, DB::table('ticket_events')->where('ticket_id', $this->ticket->getKey())->count());
    }

    public function test_a_proposed_category_must_be_one_that_exists(): void
    {
        DB::table('ticket_categories')->insert([
            'name_en' => 'Billing', 'name_ar' => 'الفوترة', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->enable(AiCapability::CategoryProposal);
        // An id from another deployment, or an invented one.
        $this->answering('9999');

        $this->assist('category')->assertJsonPath('data.proposal', null);
    }

    public function test_the_category_proposal_sends_the_subject_and_description_only(): void
    {
        DB::table('ticket_categories')->insert([
            'name_en' => 'Billing', 'name_ar' => 'الفوترة', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->enable(AiCapability::CategoryProposal);
        $this->answering('1');

        $this->say('I am furious about this and I want somebody fired.');

        $this->assist('category')->assertOk();

        $sent = implode("\n", $this->app->make(AiTransport::class)->sent);

        /*
         * The minimum. An agent asking which box a request belongs in does not
         * need six months of back-and-forth — often the emotional parts of it
         * — leaving the building to answer.
         */
        $this->assertStringContainsString('The invoice is wrong', $sent);
        $this->assertStringContainsString('duplicate charge', $sent);
        $this->assertStringNotContainsString('somebody fired', $sent);
    }

    public function test_articles_are_ranked_from_the_knowledge_base_and_nowhere_else(): void
    {
        $article = $this->publishedArticle('Refunding a duplicate charge');

        $this->enable(AiCapability::SuggestedArticles);
        // The model answers with a real id and an invented one.
        $this->answering($article.' invented-id');

        $response = $this->assist('articles');

        $response->assertOk();

        $ids = array_column($response->json('data.articles'), 'id');

        /*
         * A subset of what Postgres found, never an id the model produced on
         * its own. That two-step is what keeps suggestions inside the
         * knowledge base — a model asked to suggest an article from nothing
         * will write one — and why there is no vector store here.
         */
        $this->assertSame([$article], $ids);
    }

    public function test_staff_suggestions_may_include_internal_articles(): void
    {
        $internal = $this->publishedArticle('How we handle duplicate charges', internal: true);

        $this->enable(AiCapability::SuggestedArticles);
        $this->answering($internal);

        $ids = array_column($this->assist('articles')->json('data.articles'), 'id');

        // An internal article is written precisely so an agent can read it,
        // and nothing in this story reaches a customer surface.
        $this->assertSame([$internal], $ids);
    }

    public function test_every_surface_is_absent_when_its_capability_is_off(): void
    {
        $this->answering('Something the provider would have said.');
        $this->say('I was charged twice.');

        // Nothing enabled. Every assist answers with its degraded value.
        $this->assist('summary')->assertOk()->assertJsonPath('data.summary', null);
        $this->assist('reply')->assertOk()->assertJsonPath('data.suggestions', []);
        $this->assist('category')->assertOk()->assertJsonPath('data.proposal', null);
        $this->assist('articles')->assertOk()->assertJsonPath('data.articles', []);
    }

    public function test_one_capability_on_leaves_the_others_off(): void
    {
        $this->enable(AiCapability::Summary);
        $this->answering('A summary.');
        $this->say('I was charged twice.');

        $this->assertNotNull($this->assist('summary')->json('data.summary'));

        // Disabling any one removes its surface and leaves the others
        // untouched — and with all of them off the screen is complete, not
        // gap-toothed.
        $this->assertSame([], $this->assist('reply')->json('data.suggestions'));
        $this->assertSame([], $this->assist('articles')->json('data.articles'));
    }

    public function test_an_unreachable_provider_is_absence_not_an_error(): void
    {
        $this->enable(...AiCapability::cases());

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

        $this->say('I was charged twice.');

        /*
         * 200 with nothing in it, never a 502 and never a retry hint. The
         * screen above renders complete without the panel, which is what makes
         * the whole product correct with AI switched off.
         */
        $this->assist('summary')->assertOk()->assertJsonPath('data.summary', null);
        $this->assist('reply')->assertOk()->assertJsonPath('data.suggestions', []);
    }

    public function test_somebody_who_cannot_read_the_ticket_gets_nothing(): void
    {
        $customer = $this->makeUser(Roles::CUSTOMER);

        $this->actingAs($customer, 'web')
            ->getJson("/api/v1/tickets/{$this->ticket->getKey()}/assist/summary")
            ->assertForbidden();
    }

    public function test_there_is_no_route_that_applies_or_sends_anything(): void
    {
        $routes = (string) file_get_contents(base_path('routes/api.php'));

        $start = strpos($routes, "prefix('tickets/{ticket}/assist')");
        $this->assertNotFalse($start);

        $end = strpos($routes, '});', $start);
        $group = substr($routes, $start, (int) $end - $start);

        /*
         * Four reads and no fifth. An accept, apply or send route here would
         * be the automation this whole epic refuses — and it would look, in a
         * diff, exactly like a convenience.
         */
        $this->assertSame(4, substr_count($group, 'Route::get('));
        $this->assertStringNotContainsString('Route::post(', $group);
        $this->assertStringNotContainsString('Route::patch(', $group);
    }

    private function publishedArticle(string $title, bool $internal = false): string
    {
        $id = (string) \Illuminate\Support\Str::ulid();

        $category = (int) DB::table('article_categories')->insertGetId([
            'name_en' => 'Billing', 'name_ar' => 'الفوترة', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('articles')->insert([
            'id' => $id,
            'category_id' => $category,
            'type' => 'faq',
            'status' => 'published',
            'internal_only' => $internal,
            'default_locale' => 'en',
            'has_been_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('article_translations')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'article_id' => $id,
            'locale' => 'en',
            'title' => $title,
            'body' => 'A duplicate charge is refunded within three working days.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
