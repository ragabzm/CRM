<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A ticket that is going wrong, marked as such.
 *
 * The two rules worth the most attention:
 *
 * Escalation is a PROPERTY, not a status. Half of these tests exist to prove
 * that the lifecycle still has exactly four values and that nothing anywhere
 * treats "escalated" as a fifth.
 *
 * And it carries no version. Every other mutation is guarded, because two
 * people changing priority in opposite directions is a real conflict. Two
 * people escalating the same ticket are not in conflict — they agree, and
 * refusing the second would be refusing the agreement.
 */
final class EscalationTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use MakesTickets;
    use RefreshDatabase;

    private const REASON = 'The customer has waited four days for a part nobody ordered.';

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = $this->setUpTickets(Roles::AGENT);
        Notification::fake();
    }

    private function ticket(array $attributes = []): Ticket
    {
        return $this->makeTicket([
            'customer_id' => $this->customerId,
            'department_id' => $this->departmentId,
            'assignee_id' => $this->agent->getKey(),
            ...$attributes,
        ]);
    }

    private function escalate(Ticket $ticket, string $reason = self::REASON): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->postJson(
            '/api/v1/tickets/'.$ticket->getKey().'/escalate',
            ['reason' => $reason],
        );
    }

    public function test_escalating_records_who_and_why(): void
    {
        $ticket = $this->ticket();

        $response = $this->escalate($ticket);

        $response->assertOk();
        $this->assertNotNull($response->json('escalated_at'));
        $this->assertSame((string) $this->agent->getKey(), $response->json('escalated_by'));
        $this->assertSame(self::REASON, $response->json('escalation_reason'));
    }

    public function test_a_reason_is_required_and_has_to_say_something(): void
    {
        $ticket = $this->ticket();

        $this->withIdempotencyKey()
            ->postJson('/api/v1/tickets/'.$ticket->getKey().'/escalate', [])
            ->assertStatus(422);

        $this->escalate($ticket, '  ')->assertStatus(422);

        /*
         * An escalation with no reason reaches a supervisor as an alarm they
         * cannot act on without opening the ticket and working out for
         * themselves what the problem was — which is the work escalating was
         * supposed to save them.
         */
        $this->assertNull($ticket->refresh()->escalated_at);
    }

    public function test_the_status_does_not_move_and_there_is_no_escalated_state(): void
    {
        foreach (['open', 'pending', 'resolved', 'closed'] as $status) {
            $ticket = $this->ticket(['status' => $status]);

            $this->escalate($ticket)->assertOk();

            // Whichever of the four it had, it keeps.
            $this->assertSame($status, $ticket->refresh()->status->value);
        }

        /*
         * And the vocabulary is unchanged. A fifth value would have to be
         * learned by every filter, every count and every transition rule.
         */
        $this->assertSame(
            ['open', 'pending', 'resolved', 'closed'],
            \App\Modules\Tickets\Domain\Enum\TicketStatus::values(),
        );
    }

    public function test_escalating_carries_no_version_and_changes_none(): void
    {
        $ticket = $this->ticket();
        $before = $ticket->version;

        /*
         * No `If-Match`, and the request succeeds. Bumping the version would
         * invalidate the screen of every colleague reading the ticket, forcing
         * a reload before they could change anything — for a change that
         * altered nothing they were looking at.
         */
        $this->escalate($ticket)->assertOk();

        $this->assertSame($before, $ticket->refresh()->version);
    }

    public function test_two_people_escalating_the_same_ticket_both_succeed(): void
    {
        $ticket = $this->ticket();

        $this->escalate($ticket, 'The customer is threatening to leave.')->assertOk();

        $supervisor = User::factory()->create();
        $supervisor->syncRoles([Roles::SUPERVISOR]);
        $this->actingAs($supervisor->refresh());

        // They agree. Refusing the second would be refusing the agreement.
        $this->escalate($ticket, 'I saw this too.')->assertOk();

        // One escalation, keeping who first raised it and why.
        $this->assertSame(
            'The customer is threatening to leave.',
            $ticket->refresh()->escalation_reason,
        );
        $this->assertSame(
            1,
            DB::table('ticket_events')->where('event_type', 'ticket.escalated')->count(),
        );
    }

    public function test_it_is_recorded_in_history_with_an_actor_and_a_time(): void
    {
        $ticket = $this->ticket();

        $this->escalate($ticket)->assertOk();

        $event = DB::table('ticket_events')
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', 'ticket.escalated')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('staff', $event->actor_type);
        $this->assertSame((string) $this->agent->getKey(), $event->actor_id);
        $this->assertNotNull($event->created_at);

        // The reason travels with the event, so the history explains itself.
        $this->assertStringContainsString('part nobody ordered', (string) $event->payload);
    }

    public function test_the_history_entry_cannot_be_edited_or_deleted_through_any_route(): void
    {
        $ticket = $this->ticket();
        $this->escalate($ticket)->assertOk();

        $eventId = DB::table('ticket_events')->where('event_type', 'ticket.escalated')->value('id');

        // UX-14. There is no route, and asking for one is a 404 or a 405 —
        // never a success.
        foreach (['patch', 'put', 'delete'] as $method) {
            $response = $this->withIdempotencyKey()
                ->json(strtoupper($method), "/api/v1/tickets/{$ticket->getKey()}/events/{$eventId}");

            $this->assertContains($response->status(), [404, 405], "A {$method} on a history entry was accepted.");
        }

        $this->assertSame(1, DB::table('ticket_events')->where('event_type', 'ticket.escalated')->count());
    }

    public function test_staff_see_it_on_the_ticket_and_in_the_list(): void
    {
        $escalated = $this->ticket();
        $this->ticket();

        $this->escalate($escalated)->assertOk();

        $detail = $this->getJson('/api/v1/tickets/'.$escalated->getKey());
        $detail->assertOk();
        $this->assertNotNull($detail->json('escalated_at'));

        $list = $this->getJson('/api/v1/tickets');
        $list->assertOk();

        $flags = array_column($list->json('data'), 'escalated_at', 'id');
        $this->assertNotNull($flags[(string) $escalated->getKey()]);
    }

    public function test_escalated_is_a_filter_on_the_list_and_composes_with_status(): void
    {
        $escalatedOpen = $this->ticket(['status' => 'open']);
        $escalatedPending = $this->ticket(['status' => 'pending']);
        $plainOpen = $this->ticket(['status' => 'open']);

        $this->escalate($escalatedOpen)->assertOk();
        $this->escalate($escalatedPending)->assertOk();

        $ids = static fn (array $body): array => array_column($body['data'], 'id');

        $this->assertEqualsCanonicalizing(
            [(string) $escalatedOpen->getKey(), (string) $escalatedPending->getKey()],
            $ids($this->getJson('/api/v1/tickets?escalated=1')->json()),
        );

        $this->assertEqualsCanonicalizing(
            [(string) $plainOpen->getKey()],
            $ids($this->getJson('/api/v1/tickets?escalated=0')->json()),
        );

        /*
         * Narrows WITHIN the lifecycle. "Escalated and still open" is the
         * query a supervisor actually wants, and it only exists because
         * escalation is not a status competing with `open`.
         */
        $this->assertEqualsCanonicalizing(
            [(string) $escalatedOpen->getKey()],
            $ids($this->getJson('/api/v1/tickets?escalated=1&status=open')->json()),
        );
    }

    public function test_the_filter_understands_what_a_person_types_in_the_address_bar(): void
    {
        $escalated = $this->ticket();
        $plain = $this->ticket();
        $this->escalate($escalated)->assertOk();

        $ids = fn (string $query): array => array_column(
            $this->getJson('/api/v1/tickets?'.$query)->json('data'),
            'id',
        );

        /*
         * `?escalated=true` is what somebody types, and what most clients
         * send. Laravel's `boolean` rule does not accept it — and `(bool)
         * "false"` is TRUE, so a value that slipped past validation would mean
         * the opposite of what was asked. Both spellings, both directions.
         */
        foreach (['escalated=true', 'escalated=1', 'escalated=yes'] as $query) {
            $this->assertSame([(string) $escalated->getKey()], $ids($query), "Failed for ?{$query}");
        }

        foreach (['escalated=false', 'escalated=0', 'escalated=no'] as $query) {
            $this->assertSame([(string) $plain->getKey()], $ids($query), "Failed for ?{$query}");
        }
    }

    public function test_a_filter_value_that_means_nothing_is_refused_rather_than_guessed(): void
    {
        $this->ticket();

        // Refused, not read as `true`. A query that quietly means the opposite
        // of what was asked is worse than one that fails.
        $this->getJson('/api/v1/tickets?escalated=banana')->assertStatus(422);
    }

    public function test_a_customer_never_sees_it_on_the_portal(): void
    {
        $ticket = $this->ticket();
        $this->escalate($ticket)->assertOk();

        /*
         * The staff session is ended first, deliberately.
         *
         * Switching `actingAs` alone leaves the web guard resolved from the
         * session this test already established, and the portal routes answer
         * 401 — which would look like the customer being refused rather than
         * the test still being signed in as an agent.
         */
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $this->flushSession();

        $account = $this->portalAccountFor($this->customerId);
        $this->actingAs($account, 'portal');

        foreach ([
            '/api/v1/portal/requests',
            '/api/v1/portal/requests/'.$ticket->getKey(),
            '/api/v1/portal/tickets',
        ] as $path) {
            $response = $this->getJson($path);
            $response->assertOk();

            /*
             * Checked against the RAW RESPONSE, not against a shape. A leak
             * would arrive as an unexpected key, and asserting on the keys we
             * expected would miss precisely that.
             */
            $body = (string) $response->getContent();

            $this->assertStringNotContainsString('escalat', $body, "Escalation leaked to the customer at {$path}.");
            $this->assertStringNotContainsString('part nobody ordered', $body);
        }
    }

    /** A verified portal account for this customer. */
    private function portalAccountFor(string $customerId): \App\Modules\Portal\Domain\PortalAccount
    {
        $account = new \App\Modules\Portal\Domain\PortalAccount;

        $account->forceFill([
            'customer_id' => $customerId,
            'name' => 'Hana Yousef',
            'email' => 'hana.portal@example.test',
            'password' => 'a-long-enough-passphrase',
            'preferred_locale' => 'en',
        ])->save();

        return $account->refresh();
    }
}
