<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Portal\Domain\PortalAccount;
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
 * A customer's own requests, and nothing else.
 *
 * Two properties carry the whole story: a customer sees only their own, and
 * what they see contains nothing the desk keeps to itself. Both are enforced at
 * the API rather than in the interface — a UI that filters is a UI one bug away
 * from not filtering.
 */
final class PortalRequestsTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private PortalAccount $account;

    private string $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->make(SettingsRegistry::class)->set('email.enabled', false, null);
        $this->setUpSpaOrigin();

        $this->customerId = $this->makeCustomer();

        $this->account = new PortalAccount;
        $this->account->forceFill([
            'name' => 'Hana Yousef',
            'email' => 'hana@example.test',
            'password' => 'a-long-enough-passphrase',
            'preferred_locale' => 'en',
            'customer_id' => $this->customerId,
        ])->save();
    }

    private function asCustomer(): static
    {
        return $this->actingAs($this->account, 'portal');
    }

    private function submit(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->asCustomer()->withIdempotencyKey()->postJson('/api/v1/portal/requests', [
            'subject' => 'My invoice is wrong',
            'description' => 'I was charged twice in August.',
            ...$overrides,
        ]);
    }

    public function test_a_customer_submits_a_request(): void
    {
        $body = $this->submit()->assertStatus(201)->json();

        $this->assertSame('My invoice is wrong', $body['subject']);
        $this->assertSame('open', $body['status']);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_a_category_is_optional(): void
    {
        /*
         * A customer who does not know which category their problem belongs to
         * should still be able to ask. Sorting it is the desk's job, and a
         * required dropdown is where people give up.
         */
        $this->submit()->assertStatus(201);
        $this->assertNull(Ticket::query()->value('category_id'));
    }

    public function test_they_see_their_own_requests(): void
    {
        $this->submit()->assertStatus(201);

        $data = $this->asCustomer()->getJson('/api/v1/portal/requests')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('My invoice is wrong', $data[0]['subject']);
    }

    public function test_they_see_every_status_including_closed(): void
    {
        foreach (['open', 'pending', 'closed'] as $status) {
            $this->makeTicket(['customer_id' => $this->customerId, 'status' => $status]);
        }

        $statuses = array_column(
            $this->asCustomer()->getJson('/api/v1/portal/requests')->assertOk()->json('data'),
            'status',
        );

        // A portal that hid closed requests would look like it had lost them.
        sort($statuses);
        $this->assertSame(['closed', 'open', 'pending'], $statuses);
    }

    public function test_they_never_see_somebody_elses_request(): void
    {
        $this->makeTicket(['customer_id' => $this->makeCustomer()]);

        $this->assertSame([], $this->asCustomer()->getJson('/api/v1/portal/requests')->json('data'));
    }

    public function test_guessing_another_customers_id_is_not_found(): void
    {
        $theirs = $this->makeTicket(['customer_id' => $this->makeCustomer()]);

        /*
         * 404, never 403. A 403 confirms the id exists, and ULIDs are guessable
         * enough in bulk that confirming would let somebody count this
         * business's customers and requests from outside.
         */
        $this->asCustomer()
            ->getJson("/api/v1/portal/requests/{$theirs->getKey()}")
            ->assertStatus(404)
            ->assertJsonPath('code', 'portal.request_not_found');
    }

    public function test_replying_to_another_customers_request_is_not_found(): void
    {
        $theirs = $this->makeTicket(['customer_id' => $this->makeCustomer()]);

        $this->asCustomer()
            ->withIdempotencyKey()
            ->postJson("/api/v1/portal/requests/{$theirs->getKey()}/replies", ['body' => 'Hello'])
            ->assertStatus(404);

        // And nothing was written into a stranger's thread.
        $this->assertSame(0, DB::table('ticket_messages')->count());
    }

    public function test_the_thread_never_carries_an_internal_note(): void
    {
        $ticket = $this->makeTicket(['customer_id' => $this->customerId]);
        $agent = $this->makeUser(Roles::AGENT);
        $append = $this->app->make(AppendMessage::class);
        $actor = Actor::staff((string) $agent->getKey(), $agent->name);

        $append->handle($actor, (string) $ticket->getKey(), MessageDirection::Outbound, 'Looking into it.');
        $append->handle($actor, (string) $ticket->getKey(), MessageDirection::Internal, 'Second time this month — check the billing run.');

        $body = (string) $this->asCustomer()
            ->getJson("/api/v1/portal/requests/{$ticket->getKey()}")
            ->assertOk()
            ->getContent();

        /*
         * The failure in this whole product that cannot be taken back: a note
         * is a colleague's private remark ABOUT the person who would be reading
         * it.
         */
        $this->assertStringNotContainsString('check the billing run', $body);
        $this->assertStringContainsString('Looking into it.', $body);
    }

    public function test_the_thread_does_not_name_the_agent(): void
    {
        $ticket = $this->makeTicket(['customer_id' => $this->customerId]);
        $agent = $this->makeUser(Roles::AGENT);

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            MessageDirection::Outbound,
            'Looking into it.',
        );

        $message = $this->asCustomer()
            ->getJson("/api/v1/portal/requests/{$ticket->getKey()}")
            ->assertOk()
            ->json('messages.0');

        /*
         * "support", not a name. Who exactly is the desk's business, and a name
         * here follows that agent into every later conversation — the customer
         * asks for them by name and the desk becomes a set of personal queues.
         */
        $this->assertSame('support', $message['from']);
        $this->assertArrayNotHasKey('author', $message);
    }

    public function test_no_sla_countdown_reaches_a_customer(): void
    {
        $ticket = $this->makeTicket(['customer_id' => $this->customerId]);

        $body = $this->asCustomer()->getJson("/api/v1/portal/requests/{$ticket->getKey()}")->json();

        /*
         * A countdown a customer can watch is a promise nobody made to them —
         * and one they will quote back the moment it runs out.
         */
        foreach (['sla', 'priority', 'assignee_id', 'department_id', 'version'] as $key) {
            $this->assertArrayNotHasKey($key, $body, "The portal leaked [{$key}].");
        }
    }

    public function test_a_reply_appends_to_the_thread(): void
    {
        $ticket = $this->makeTicket(['customer_id' => $this->customerId]);

        $body = $this->asCustomer()
            ->withIdempotencyKey()
            ->postJson("/api/v1/portal/requests/{$ticket->getKey()}/replies", ['body' => 'Any news?'])
            ->assertOk()
            ->json();

        $this->assertSame('you', $body['messages'][0]['from']);
        $this->assertSame('Any news?', $body['messages'][0]['body']);
    }

    public function test_a_reply_wakes_a_pending_request(): void
    {
        $ticket = $this->makeTicket(['customer_id' => $this->customerId, 'status' => 'pending']);

        $this->asCustomer()
            ->withIdempotencyKey()
            ->postJson("/api/v1/portal/requests/{$ticket->getKey()}/replies", ['body' => 'Here it is.'])
            ->assertOk();

        // A ticket left Pending after the customer answers drops out of every
        // queue, and the person waiting is the one who did what we asked.
        $this->assertSame('open', $ticket->refresh()->status->value);
    }

    public function test_the_response_says_nothing_about_the_transition(): void
    {
        $ticket = $this->makeTicket(['customer_id' => $this->customerId, 'status' => 'pending']);

        $body = (string) $this->asCustomer()
            ->withIdempotencyKey()
            ->postJson("/api/v1/portal/requests/{$ticket->getKey()}/replies", ['body' => 'Here it is.'])
            ->getContent();

        /*
         * A customer does not need to be told the internal state machine
         * moved, and telling them invites a question about a word that means
         * nothing to them.
         */
        $this->assertStringNotContainsString('pending', $body);
        $this->assertStringNotContainsString('notification', $body);
    }

    public function test_an_empty_reply_is_refused(): void
    {
        $ticket = $this->makeTicket(['customer_id' => $this->customerId]);

        $this->asCustomer()
            ->withIdempotencyKey()
            ->postJson("/api/v1/portal/requests/{$ticket->getKey()}/replies", ['body' => '   '])
            ->assertStatus(422);
    }

    public function test_an_unlinked_account_cannot_submit(): void
    {
        $this->account->forceFill(['customer_id' => null])->save();

        // Guessing would attach a stranger's words to somebody's record.
        $this->submit()->assertStatus(403)->assertJsonPath('code', 'portal.account_unlinked');
    }

    public function test_an_unauthenticated_caller_sees_nothing(): void
    {
        $this->makeTicket(['customer_id' => $this->customerId]);

        $this->getJson('/api/v1/portal/requests')->assertStatus(401);
    }
}
