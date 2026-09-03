<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Portal\Domain\PortalAccount;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * A customer opening their own ticket.
 *
 * The same command as the agent path, so the reference, the event and the
 * version behave identically. What differs is that the customer and the channel
 * are decided by the server, never taken from the request.
 */
final class PortalCreateTicketTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sets up the customer and department; the staff actor is replaced
        // below by a portal one.
        $this->setUpTickets();
    }

    private ?PortalAccount $account = null;

    private function signInAsPortal(?string $customerId = null): PortalAccount
    {
        $this->account = PortalAccount::query()->create([
            'name' => 'Hana Yousef',
            'email' => 'hana+'.uniqid().'@example.test',
            'password' => Hash::make('Correct-Horse-9'),
            'customer_id' => $customerId,
        ]);

        return $this->asPortal();
    }

    /**
     * Re-presents the portal identity.
     *
     * Called before EVERY request rather than once: actingAs() authenticates a
     * single request, while a browser sends its cookie on all of them. Without
     * this the second request in a test is anonymous, which is a fact about the
     * test harness and not about the product.
     */
    private function asPortal(): PortalAccount
    {
        /*
         * The session is flushed first. Sanctum's AuthenticateSession compares
         * a password hash it stores in the session against the user's, and
         * actingAs() never writes that hash — so the session left behind by a
         * previous successful request logs the guard straight back out. A real
         * browser has a session that agrees with itself; this is the closest
         * equivalent.
         */
        $this->flushSession();
        $this->actingAs($this->account, 'portal');

        return $this->account;
    }

    /** A second customer, so "only their own" has something to exclude. */
    private function anotherCustomer(): string
    {
        $customer = new \App\Modules\Customers\Domain\Customer([
            'reference' => \App\Modules\Customers\Domain\Customer::mintReference(),
            'full_name' => 'Someone Else',
            'department_id' => $this->departmentId,
            'state' => 'active',
        ]);
        $customer->setAttribute('id', (string) \Illuminate\Support\Str::ulid());
        $customer->save();

        return (string) $customer->getKey();
    }

    /** @param array<string, mixed> $body */
    private function submit(array $body = []): \Illuminate\Testing\TestResponse
    {
        if ($this->account !== null) {
            $this->asPortal();
        }

        return $this->withIdempotencyKey()->postJson('/api/v1/portal/tickets', [
            'subject' => 'My invoice is wrong',
            'description' => 'I was charged twice this month.',
            ...$body,
        ]);
    }

    public function test_a_customer_opens_a_ticket_from_the_portal(): void
    {
        $this->signInAsPortal($this->customerId);

        $body = $this->submit()->assertStatus(201)->json();

        // What the CUSTOMER is shown: a reference, a subject, a status.
        $this->assertSame('open', $body['status']);
        $this->assertNotEmpty($body['reference']);

        /*
         * The rest is asserted against the row rather than the response,
         * because the portal shape deliberately publishes none of it — no
         * channel, no version, no creator. Checking what was STORED is the
         * stronger test anyway: it survives the response shape changing again.
         */
        $stored = DB::table('tickets')->where('id', $body['id'])->first();

        $this->assertSame('portal', $stored->channel);
        $this->assertSame('portal', $stored->creator_type);
        $this->assertSame($this->customerId, $stored->customer_id);
        $this->assertSame(1, (int) $stored->version);
    }

    public function test_a_portal_ticket_gets_the_same_reference_and_event_treatment(): void
    {
        $this->signInAsPortal($this->customerId);

        $ticket = $this->submit()->assertStatus(201)->json();

        // The same command, so nothing about a portal ticket is second-class.
        $this->assertMatchesRegularExpression('/^TKT-\d{6,}$/', $ticket['reference']);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket['id'],
            'event_type' => TicketEvent::CREATED,
            'actor_type' => 'portal',
        ]);
    }

    public function test_the_customer_comes_from_the_account_not_the_request(): void
    {
        $this->signInAsPortal($this->customerId);

        // A customer_id in the body is ignored entirely. Honouring it would let
        // any portal user open — and then read back — a ticket against anybody
        // else's record.
        $body = $this->submit(['customer_id' => 'SOMEONEELSESCUSTOMERID0001'])
            ->assertStatus(201)->json();

        $this->assertSame(
            $this->customerId,
            DB::table('tickets')->where('id', $body['id'])->value('customer_id'),
        );
    }

    public function test_the_channel_cannot_be_claimed(): void
    {
        $this->signInAsPortal($this->customerId);

        // A portal submission claiming `agent` would misreport where the work
        // came from.
        $body = $this->submit(['channel' => 'agent'])->assertStatus(201)->json();

        $this->assertSame(
            'portal',
            DB::table('tickets')->where('id', $body['id'])->value('channel'),
        );
    }

    public function test_a_customer_cannot_set_their_own_priority_or_assignee(): void
    {
        $this->signInAsPortal($this->customerId);

        $body = $this->submit([
            'priority' => 'urgent',
            'assignee_id' => 1,
            'department_id' => $this->departmentId,
        ])->assertStatus(201)->json();

        $stored = DB::table('tickets')->where('id', $body['id'])->first();

        // Every customer would mark their own ticket urgent, and the field
        // would stop meaning anything.
        $this->assertSame('normal', $stored->priority);
        $this->assertNull($stored->assignee_id);
        $this->assertNull($stored->department_id);

        // And none of it is echoed back either: a customer reading "low" hears
        // "we do not care", and a named assignee turns a desk into a set of
        // personal queues.
        $this->assertArrayNotHasKey('priority', $body);
        $this->assertArrayNotHasKey('assignee_id', $body);
    }

    public function test_an_unlinked_account_is_refused_rather_than_guessed(): void
    {
        $this->signInAsPortal(null);

        $response = $this->submit()->assertStatus(403);

        // Guessing would attach a stranger's message to somebody's record.
        $response->assertJsonPath('code', 'portal.account_unlinked');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_a_customer_sees_only_their_own_tickets(): void
    {
        $this->signInAsPortal($this->customerId);
        $mine = $this->submit()->assertStatus(201)->json('id');

        // A ticket belonging to somebody else entirely, created directly so
        // this test does not depend on a second portal session.
        $stranger = new Ticket;
        $stranger->forceFill([
            'reference' => 'TKT-999999',
            'subject' => 'Not yours',
            'description' => 'Someone else entirely',
            'customer_id' => $this->anotherCustomer(),
            'channel' => 'portal',
            'status' => 'open',
            'priority' => 'normal',
            'creator_type' => 'portal',
            'creator_id' => '999',
            'version' => 1,
        ])->save();

        $this->asPortal();
        $tickets = $this->getJson('/api/v1/portal/tickets')->assertOk()->json('data');

        // Scoped to the account's own customer. Seeing a stranger's ticket
        // would be the worst possible leak on a customer-facing surface.
        $this->assertCount(1, $tickets);
        $this->assertSame($mine, $tickets[0]['id']);
    }

    public function test_a_signed_out_visitor_cannot_open_a_ticket(): void
    {
        $this->submit()->assertStatus(401);
    }

    public function test_a_staff_session_does_not_reach_the_portal_surface(): void
    {
        // Staff and portal customers are separate populations on separate
        // guards; a staff cookie must not authenticate a portal route.
        $this->actingAsRole(\App\Modules\Security\Domain\Roles::SUPERVISOR);

        $this->submit()->assertStatus(401);
    }

    public function test_a_subject_and_description_are_still_required(): void
    {
        $this->signInAsPortal($this->customerId);

        $this->asPortal();
        $this->withIdempotencyKey()->postJson('/api/v1/portal/tickets', ['subject' => 'Only a subject'])
            ->assertStatus(422);
    }
}
