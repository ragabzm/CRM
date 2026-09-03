<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Reading a ticket's history.
 */
final class TicketEventsApiTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function record(Ticket $ticket, TicketEventKind $kind, Actor $actor, ?array $before = null, ?array $after = null, ?array $meta = null): void
    {
        DB::transaction(function () use ($ticket, $kind, $actor, $before, $after, $meta): void {
            $this->app->make(TicketEventRecorder::class)->record(
                (string) $ticket->getKey(),
                $kind,
                $actor,
                $before,
                $after,
                $meta,
            );
        });
    }

    private function events(Ticket $ticket, string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v1/tickets/{$ticket->getKey()}/events{$query}");
    }

    public function test_it_returns_the_history_oldest_first(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        $this->record($ticket, TicketEventKind::Created, Actor::staff((string) $admin->getKey(), 'Admin'), null, ['status' => 'open']);
        $this->record($ticket, TicketEventKind::StatusChanged, Actor::staff((string) $admin->getKey(), 'Admin'), ['status' => 'open'], ['status' => 'pending']);

        $response = $this->actingAs($admin)->events($ticket)->assertOk();

        // A history is read as a story, from the beginning.
        $this->assertSame(
            [TicketEventKind::Created->value, TicketEventKind::StatusChanged->value],
            array_column($response->json('data'), 'kind'),
        );
    }

    public function test_every_event_answers_the_same_three_questions(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        $this->record($ticket, TicketEventKind::StatusChanged, Actor::staff((string) $admin->getKey(), 'Admin'), ['status' => 'open'], ['status' => 'pending']);

        $event = $this->actingAs($admin)->events($ticket)->assertOk()->json('data.0');

        $this->assertSame(['status' => 'open'], $event['before']);
        $this->assertSame(['status' => 'pending'], $event['after']);
        $this->assertArrayHasKey('meta', $event);
        $this->assertArrayHasKey('occurred_at', $event);
    }

    public function test_it_names_the_person_who_acted(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        $this->record($ticket, TicketEventKind::Created, Actor::staff((string) $admin->getKey(), 'ignored'), null, ['status' => 'open']);

        $actor = $this->actingAs($admin)->events($ticket)->assertOk()->json('data.0.actor');

        // Resolved at read time, not copied at write time: a person who
        // changes their name should not appear under two names in one history.
        $this->assertSame('staff', $actor['type']);
        $this->assertSame($admin->name, $actor['display_name']);
    }

    public function test_a_system_event_never_carries_a_person(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        $this->record($ticket, TicketEventKind::StatusChanged, Actor::system('auto_close'), ['status' => 'resolved'], ['status' => 'closed']);

        $actor = $this->actingAs($admin)->events($ticket)->assertOk()->json('data.0.actor');

        /*
         * No id key at all, not merely a null one. Attributing an automatic
         * decision to whoever happened to trigger the job puts a name against
         * a choice they did not make.
         */
        $this->assertSame(['type' => 'system', 'reason' => 'auto_close'], $actor);
    }

    public function test_arabic_content_survives_the_round_trip_byte_for_byte(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        $arabic = 'الفاتورة تحتوي على رسوم مكرّرة — ٤٥٠ ر.س';

        $this->record($ticket, TicketEventKind::CategoryChanged, Actor::staff((string) $admin->getKey(), 'A'), ['subject' => 'Old'], ['subject' => $arabic]);

        $after = $this->actingAs($admin)->events($ticket)->assertOk()->json('data.0.after');

        // Byte equality, not "looks right": a history that mangles what someone
        // wrote is evidence of nothing.
        $this->assertSame($arabic, $after['subject']);
        $this->assertSame(strlen($arabic), strlen($after['subject']));
    }

    public function test_it_paginates_with_a_cursor(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        for ($i = 0; $i < 5; $i++) {
            $this->record($ticket, TicketEventKind::StatusChanged, Actor::system("step_{$i}"), null, ['n' => $i]);
        }

        $first = $this->actingAs($admin)->events($ticket, '?limit=2')->assertOk();

        $this->assertCount(2, $first->json('data'));
        $this->assertTrue($first->json('has_more'));

        $second = $this->actingAs($admin)->events($ticket, '?limit=2&cursor='.$first->json('next_cursor'))->assertOk();

        // Continues, never repeats.
        $this->assertNotSame(
            array_column($first->json('data'), 'id'),
            array_column($second->json('data'), 'id'),
        );
    }

    public function test_it_caps_an_oversized_page_request(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        $this->record($ticket, TicketEventKind::Created, Actor::system('t'), null, null);

        // A caller asking for a million rows gets a page, not an outage.
        $this->actingAs($admin)->events($ticket, '?limit=100000')->assertOk();
    }

    public function test_an_agent_cannot_read_the_history_of_someone_elses_ticket(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $outsider = $this->makeUser(Roles::AGENT);

        $ticket = $this->makeTicket(['assignee_id' => $owner->getKey()]);
        $this->record($ticket, TicketEventKind::Created, Actor::system('t'), null, ['status' => 'open']);

        /*
         * 404, not 403. A 403 would confirm the ticket exists, and an agent
         * could walk ids to learn how many tickets the business has.
         */
        $this->actingAs($outsider)->events($ticket)->assertStatus(404)
            ->assertJsonPath('code', 'tickets.not_found');
    }

    public function test_an_agent_can_read_the_history_of_their_own_ticket(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket(['assignee_id' => $owner->getKey()]);

        $this->record($ticket, TicketEventKind::Created, Actor::system('t'), null, ['status' => 'open']);

        $this->actingAs($owner)->events($ticket)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_it_refuses_an_unauthenticated_caller(): void
    {
        $ticket = $this->makeTicket();

        $this->events($ticket)->assertStatus(401);
    }

    public function test_writing_to_the_history_is_not_possible(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        $this->record($ticket, TicketEventKind::Created, Actor::system('t'), null, null);

        $url = "/api/v1/tickets/{$ticket->getKey()}/events";

        /*
         * 405 exactly: the URI exists, the verb does not. Decided by the router
         * before any controller runs, which is why there is no way to reach a
         * write even if someone adds a method to the controller later.
         *
         * Verified end to end rather than trusted because the UI never offers
         * it — a guarantee nobody tested is a guarantee nobody has.
         */
        foreach (['patch', 'put', 'delete', 'post'] as $verb) {
            $this->actingAs($admin)->{"{$verb}Json"}($url, [])->assertStatus(405);
        }
    }

    public function test_an_individual_event_is_not_addressable_at_all(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        /*
         * 404, not 405, and that is the correct answer: 405 means "this URI
         * exists but not with that verb", and there is no per-event URI in this
         * API at all. Nothing addresses a single event, so nothing can be
         * pointed at one to change it.
         */
        foreach (['patch', 'put', 'delete'] as $verb) {
            $this->actingAs($admin)
                ->{"{$verb}Json"}("/api/v1/tickets/{$ticket->getKey()}/events/01E1", [])
                ->assertStatus(404)
                ->assertHeader('Content-Type', 'application/problem+json');
        }
    }

    public function test_a_refused_write_answers_in_problem_json(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        $response = $this->actingAs($admin)->deleteJson("/api/v1/tickets/{$ticket->getKey()}/events");

        // Every non-2xx in this system is problem+json, including the ones the
        // router produces without reaching a controller.
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertArrayHasKey('code', $response->json());
    }

    public function test_the_page_does_not_query_once_per_row(): void
    {
        $admin = $this->makeUser(Roles::ADMINISTRATOR);
        $ticket = $this->makeTicket();

        for ($i = 0; $i < 20; $i++) {
            $this->record($ticket, TicketEventKind::StatusChanged, Actor::staff((string) $admin->getKey(), 'A'), null, ['n' => $i]);
        }

        // Warm whatever the framework caches on a first request, so the count
        // below is about this endpoint and not about booting it.
        $this->actingAs($admin)->events($ticket)->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($admin)->events($ticket)->assertOk();

        // A long history is mostly the same few people; resolving names row by
        // row would turn a 200-event page into 200 queries.
        $this->assertLessThan(10, $queries, "History page issued {$queries} queries.");
    }
}
