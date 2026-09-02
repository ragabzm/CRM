<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Customers\Domain\Customer;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Application\Timeline\TimelineEntry;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * One reverse-chronological feed of everything that happened for a customer.
 */
final class CustomerTimelineTest extends TestCase
{
    use InteractsWithLifecycle;
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTickets();
    }

    private function timeline(string $query = '', ?string $customerId = null): array
    {
        $customerId ??= $this->customerId;

        return $this->getJson("/api/v1/customers/{$customerId}/timeline".($query !== '' ? "?{$query}" : ''))
            ->assertOk()
            ->json();
    }

    /** A ticket opened at a fixed moment. */
    private function ticketAt(string $at, ?string $customerId = null): Ticket
    {
        Carbon::setTestNow($at);
        $ticket = $this->makeTicket();

        if ($customerId !== null) {
            $ticket->forceFill(['customer_id' => $customerId])->save();
        }

        Carbon::setTestNow();

        return $ticket->refresh();
    }

    private function messageAt(Ticket $ticket, string $at, MessageDirection $direction, string $body): TicketMessage
    {
        Carbon::setTestNow($at);

        $message = $this->app->make(AppendMessage::class)->handle(
            Actor::staff('1', 'Agent'),
            (string) $ticket->getKey(),
            $direction,
            $body,
        );

        Carbon::setTestNow();

        return $message;
    }

    public function test_tickets_and_messages_share_one_feed(): void
    {
        $ticket = $this->ticketAt('2026-09-01 09:00:00');
        $this->messageAt($ticket, '2026-09-01 10:00:00', MessageDirection::Inbound, 'Any news?');
        $this->messageAt($ticket, '2026-09-01 11:00:00', MessageDirection::Outbound, 'Looking now.');

        $this->actingAsRole(Roles::AGENT);

        $kinds = array_column($this->timeline()['data'], 'kind');

        // Newest first, and both kinds interleaved rather than grouped.
        $this->assertSame([
            TimelineEntry::MESSAGE_OUTBOUND,
            TimelineEntry::MESSAGE_INBOUND,
            TimelineEntry::TICKET_OPENED,
        ], $kinds);
    }

    public function test_every_row_is_identifiable_at_a_glance(): void
    {
        $ticket = $this->ticketAt('2026-09-01 09:00:00');
        $this->actingAsRole(Roles::AGENT);

        $row = $this->timeline()['data'][0];

        // Kind, when, and which ticket — without a second request.
        $this->assertSame(TimelineEntry::TICKET_OPENED, $row['kind']);
        $this->assertSame((string) $ticket->getKey(), $row['ticket_id']);
        $this->assertSame($ticket->reference, $row['ticket_ref']);
        $this->assertStringEndsWith('Z', $row['occurred_at']);
        // A ticket opening has no text of its own.
        $this->assertNull($row['preview']);
    }

    public function test_a_message_carries_a_preview_not_its_whole_body(): void
    {
        $ticket = $this->ticketAt('2026-09-01 09:00:00');
        $long = str_repeat('alpha beta ', 60);
        $this->messageAt($ticket, '2026-09-01 10:00:00', MessageDirection::Inbound, $long);

        $this->actingAsRole(Roles::AGENT);

        $preview = $this->timeline()['data'][0]['preview'];

        /*
         * Truncated server-side. Sending whole bodies to render one line each
         * is the difference between a page that loads and one that does not on
         * a customer with a long history.
         */
        $this->assertLessThanOrEqual(TicketMessage::PREVIEW_LENGTH + 1, mb_strlen($preview));
        $this->assertLessThan(mb_strlen($long), mb_strlen($preview));
    }

    public function test_only_this_customers_history_appears(): void
    {
        $mine = $this->ticketAt('2026-09-01 09:00:00');

        $stranger = new Customer([
            'reference' => Customer::mintReference(),
            'full_name' => 'Someone Else',
            'department_id' => $this->departmentId,
            'state' => 'active',
        ]);
        $stranger->setAttribute('id', (string) Str::ulid());
        $stranger->save();

        $theirs = $this->ticketAt('2026-09-01 10:00:00', (string) $stranger->getKey());

        $this->actingAsRole(Roles::AGENT);

        $ids = array_column($this->timeline()['data'], 'ticket_id');

        $this->assertContains((string) $mine->getKey(), $ids);
        $this->assertNotContains((string) $theirs->getKey(), $ids);
    }

    public function test_a_customer_with_no_history_gets_an_empty_page(): void
    {
        $this->actingAsRole(Roles::AGENT);

        $body = $this->timeline();

        // Empty, not an error: "nothing has happened yet" is an answer.
        $this->assertSame([], $body['data']);
        $this->assertNull($body['next_cursor']);
        $this->assertFalse($body['has_more']);
    }

    public function test_paging_never_repeats_or_drops_an_entry(): void
    {
        $ticket = $this->ticketAt('2026-09-01 08:00:00');

        for ($i = 0; $i < 12; $i++) {
            $this->messageAt($ticket, '2026-09-01 09:00:00', MessageDirection::Inbound, "Message {$i}");
        }

        $this->actingAsRole(Roles::AGENT);

        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $body = $this->timeline('limit=5'.($cursor !== null ? "&cursor={$cursor}" : ''));
            $seen = [...$seen, ...array_column($body['data'], 'id')];
            $cursor = $body['next_cursor'];
            $pages++;
        } while ($cursor !== null && $pages < 10);

        /*
         * Twelve messages share one timestamp to the second. Without the id
         * tiebreak in the cursor the database is free to order them differently
         * per query, which repeats some rows across pages and loses others.
         */
        $this->assertCount(13, $seen);
        $this->assertCount(13, array_unique($seen));
    }

    public function test_an_entry_added_between_pages_does_not_shift_the_window(): void
    {
        $ticket = $this->ticketAt('2026-09-01 08:00:00');

        for ($i = 0; $i < 6; $i++) {
            $this->messageAt($ticket, sprintf('2026-09-01 09:%02d:00', $i), MessageDirection::Inbound, "Old {$i}");
        }

        $this->actingAsRole(Roles::AGENT);

        $first = $this->timeline('limit=3');
        $firstIds = array_column($first['data'], 'id');

        // Something new arrives at the top while the reader is on page one.
        $this->messageAt($ticket, '2026-09-01 23:00:00', MessageDirection::Outbound, 'Brand new');

        $second = $this->timeline('limit=3&cursor='.$first['next_cursor']);
        $secondIds = array_column($second['data'], 'id');

        /*
         * The cursor keys on the last row's own position, so a new entry lands
         * above the window and changes nothing below it. An offset would have
         * pushed everything down by one and shown page one's last row again.
         */
        $this->assertSame([], array_intersect($firstIds, $secondIds));
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->actingAsRole(Roles::AGENT);

        $this->getJson("/api/v1/customers/{$this->customerId}/timeline?limit=5000")->assertStatus(422);
    }

    public function test_a_meaningless_cursor_starts_over_rather_than_failing(): void
    {
        $this->ticketAt('2026-09-01 09:00:00');
        $this->actingAsRole(Roles::AGENT);

        // Almost always a stale bookmark or a truncated URL, and starting over
        // is what the reader wanted anyway.
        $this->assertCount(1, $this->timeline('cursor=not-a-real-cursor')['data']);
    }

    public function test_an_unknown_customer_is_a_404_not_an_empty_feed(): void
    {
        $this->actingAsRole(Roles::AGENT);

        // An empty feed would read as "this customer has no history" and send
        // the agent looking for a bug that is not there.
        $this->getJson('/api/v1/customers/01JZZZZZZZZZZZZZZZZZZZZZZZ/timeline')
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'customers.not_found');
    }

    public function test_a_caller_without_customer_access_is_refused(): void
    {
        $this->ticketAt('2026-09-01 09:00:00');

        $customer = \App\Models\User::factory()->create();
        $customer->syncRoles([Roles::CUSTOMER]);
        $this->actingAs($customer->refresh());

        $this->getJson("/api/v1/customers/{$this->customerId}/timeline")
            ->assertStatus(403)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_a_guest_is_challenged(): void
    {
        $this->app['auth']->forgetGuards();
        $this->refreshApplication();
        $this->setUpSpaOrigin();

        $this->getJson("/api/v1/customers/{$this->customerId}/timeline")->assertStatus(401);
    }

    private int $queryCount = 0;

    /**
     * Counts the statements one timeline request issues.
     *
     * ONE listener for the whole test, with a counter reset between
     * measurements: registering a fresh listener per call would double-count
     * the second measurement and make a growing query count look like a
     * shrinking one.
     */
    private function queriesForTimeline(): int
    {
        $this->queryCount = 0;
        $this->timeline('limit=50');

        return $this->queryCount;
    }

    public function test_the_query_count_does_not_grow_with_the_history(): void
    {
        $ticket = $this->ticketAt('2026-09-01 08:00:00');
        $this->messageAt($ticket, '2026-09-01 09:00:00', MessageDirection::Inbound, 'One');

        $this->actingAsRole(Roles::AGENT);

        \Illuminate\Support\Facades\DB::listen(function (): void {
            $this->queryCount++;
        });

        /*
         * Warmed first. The session and the permission map are read from the
         * database on the first request of a test and cached after, so an
         * unwarmed baseline would be measuring those rather than the timeline.
         */
        $this->queriesForTimeline();

        $small = $this->queriesForTimeline();

        for ($i = 0; $i < 30; $i++) {
            $this->messageAt($ticket, sprintf('2026-09-01 10:%02d:00', $i), MessageDirection::Inbound, "M{$i}");
        }

        $large = $this->queriesForTimeline();

        /*
         * The same handful of statements for two entries and for thirty-two.
         *
         * That is the property that matters — not the absolute count, which
         * also includes the session, the permission lookup and the existence
         * check. Fetching identifiers or references per row would be the
         * classic N+1: fast on a fixture, unusable on two years of history.
         */
        $this->assertSame(
            $small,
            $large,
            "Two entries cost {$small} queries; thirty-two cost {$large}.",
        );
    }

    public function test_it_offers_no_filters(): void
    {
        $ticket = $this->ticketAt('2026-09-01 08:00:00');
        $this->messageAt($ticket, '2026-09-01 09:00:00', MessageDirection::Inbound, 'Hello');

        $this->actingAsRole(Roles::AGENT);

        // No channel filter, no date range, no kind filter — this version is
        // one list read top to bottom. A stray parameter is ignored, not
        // honoured, and not an error either.
        $filtered = $this->timeline('kind=message_inbound&from=2026-09-01&channel=email');

        $this->assertCount(2, $filtered['data']);
    }
}
