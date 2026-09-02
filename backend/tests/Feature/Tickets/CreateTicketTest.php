<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Reference\TicketReference;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class CreateTicketTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTickets();
    }

    public function test_a_ticket_is_created_open_at_version_one(): void
    {
        $ticket = $this->createTicket();

        $this->assertSame('open', $ticket['status']);
        $this->assertSame('normal', $ticket['priority']);
        $this->assertSame(1, $ticket['version']);
        // Unassigned: it goes to the pool where whoever picks up work next can
        // see it, rather than being hidden on one person's list.
        $this->assertNull($ticket['assignee_id']);
    }

    public function test_the_reference_is_human_quotable(): void
    {
        $ticket = $this->createTicket();

        $this->assertMatchesRegularExpression(TicketReference::PATTERN, $ticket['reference']);
        $this->assertTrue(TicketReference::isValid($ticket['reference']));
    }

    public function test_references_are_unique_and_ascending(): void
    {
        $references = [];

        for ($i = 0; $i < 20; $i++) {
            $references[] = $this->createTicket()['reference'];
        }

        // Distinct, or an agent quotes one reference and two tickets answer.
        $this->assertCount(20, array_unique($references));

        $sorted = $references;
        sort($sorted);
        // Ascending, so a sorted list reads in creation order.
        $this->assertSame($sorted, $references);
    }

    public function test_creation_writes_exactly_one_event(): void
    {
        $ticket = $this->createTicket();

        $events = TicketEvent::query()->where('ticket_id', $ticket['id'])->get();

        // Exactly one: a history that double-counts the creation is one nobody
        // trusts to count anything else correctly.
        $this->assertCount(1, $events);
        $this->assertSame(TicketEvent::CREATED, $events[0]->event_type);
        $this->assertSame(1, $events[0]->version_after);
    }

    public function test_the_event_records_who_created_it(): void
    {
        $user = $this->actingAsRole(Roles::AGENT, 'Hana Yousef');
        $ticket = $this->createTicket();

        $event = TicketEvent::query()->where('ticket_id', $ticket['id'])->firstOrFail();

        $this->assertSame('staff', $event->actor_type);
        $this->assertSame((string) $user->getKey(), $event->actor_id);
        $this->assertSame('staff', $ticket['creator_type']);
    }

    public function test_the_ticket_and_its_event_are_written_together(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $this->createTicket();

        $inserts = array_values(array_filter(
            $statements,
            static fn (string $sql): bool => str_contains($sql, 'insert into "tickets"')
                || str_contains($sql, 'insert into "ticket_events"'),
        ));

        // Both, in order. A ticket whose history starts mid-story cannot be
        // repaired later, so the two are one transaction.
        $this->assertCount(2, $inserts);
        $this->assertStringContainsString('tickets', $inserts[0]);
        $this->assertStringContainsString('ticket_events', $inserts[1]);
    }

    public function test_a_priority_and_category_are_recorded_when_given(): void
    {
        $ticket = $this->createTicket([
            'priority' => 'urgent',
            'category_id' => $this->categoryId,
            'department_id' => $this->departmentId,
        ]);

        $this->assertSame('urgent', $ticket['priority']);
        $this->assertSame($this->categoryId, $ticket['category_id']);
        $this->assertSame($this->departmentId, $ticket['department_id']);
    }

    public function test_a_ticket_needs_a_customer_that_exists(): void
    {
        $this->withIdempotencyKey()
            ->postJson('/api/v1/tickets', $this->ticketPayload(['customer_id' => (string) Str::ulid()]))
            ->assertStatus(422);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_a_ticket_needs_a_subject_and_a_description(): void
    {
        foreach (['subject', 'description'] as $field) {
            $payload = $this->ticketPayload();
            unset($payload[$field]);

            $this->withIdempotencyKey()->postJson('/api/v1/tickets', $payload)->assertStatus(422);
        }

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_an_unknown_channel_is_refused(): void
    {
        $this->withIdempotencyKey()
            ->postJson('/api/v1/tickets', $this->ticketPayload(['channel' => 'carrier-pigeon']))
            ->assertStatus(422);
    }

    public function test_a_replayed_create_does_not_make_a_second_ticket(): void
    {
        $key = (string) Str::ulid();
        $payload = $this->ticketPayload();

        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/tickets', $payload)->assertStatus(201);
        $second = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/tickets', $payload);

        // A double-clicked button must not open two tickets for one problem.
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame($first->json('reference'), $second->json('reference'));
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('ticket_events', 1);
    }

    public function test_an_agent_may_create_but_a_guest_may_not(): void
    {
        $this->app['auth']->forgetGuards();
        $this->refreshApplication();
        $this->setUpSpaOrigin();

        $this->withIdempotencyKey()
            ->postJson('/api/v1/tickets', ['subject' => 'x', 'description' => 'y', 'channel' => 'agent'])
            ->assertStatus(401);
    }
}
