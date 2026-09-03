<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Commands\TicketAttributeChanges;
use App\Modules\Tickets\Domain\Commands\UpdateTicketAttributes;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The history is written by the commands, inside their own transaction.
 *
 * A ticket whose history has a hole in it is worse than one with no history at
 * all: the hole is invisible, and it will be exactly the change somebody is
 * trying to explain.
 */
final class TicketEventsAppendedByCommandsTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return list<string> */
    private function kinds(Ticket $ticket): array
    {
        return TicketEvent::query()
            ->where('ticket_id', $ticket->getKey())
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('event_type')
            ->all();
    }

    private function update(Ticket $ticket, array $changes, ?int $version = null): Ticket
    {
        $agent = $this->makeUser(Roles::AGENT);

        return $this->app->make(UpdateTicketAttributes::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            $version ?? $ticket->version,
            TicketAttributeChanges::of($changes),
        );
    }

    public function test_creating_a_ticket_records_its_beginning(): void
    {
        $agent = $this->makeUser(Roles::AGENT);
        $customer = $this->makeCustomer();

        $ticket = $this->app->make(CreateTicket::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            new CreateTicketInput(
                subject: 'Duplicate charge',
                description: 'Charged twice.',
                customerId: $customer,
                channel: TicketChannel::Agent,
            ),
        );

        $this->assertSame([TicketEventKind::Created->value], $this->kinds($ticket));
    }

    public function test_the_creation_event_records_the_state_the_ticket_was_born_with(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $ticket = $this->app->make(CreateTicket::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            new CreateTicketInput(
                subject: 'Duplicate charge',
                description: 'Charged twice.',
                customerId: $this->makeCustomer(),
                channel: TicketChannel::Agent,
            ),
        );

        $event = TicketEvent::query()->where('ticket_id', $ticket->getKey())->firstOrFail();

        // `before` is null because nothing preceded it; `after` is the baseline
        // every later diff is read against.
        $this->assertNull($event->payload['before']);
        $this->assertSame('open', $event->payload['after']['status']);
        $this->assertSame(1, $event->version_after);
    }

    public function test_a_real_change_records_only_the_field_that_moved(): void
    {
        $ticket = $this->makeTicket();

        $this->update($ticket, ['priority' => 'urgent']);

        $event = TicketEvent::query()
            ->where('event_type', TicketEventKind::PriorityChanged->value)
            ->firstOrFail();

        // Not the whole row. A history that repeats unchanged values makes the
        // reader diff two blobs in their head to find the one that moved.
        $this->assertSame(['priority' => 'normal'], $event->payload['before']);
        $this->assertSame(['priority' => 'urgent'], $event->payload['after']);
    }

    public function test_setting_a_field_to_what_it_already_is_records_nothing(): void
    {
        $ticket = $this->makeTicket(['priority' => 'urgent']);

        $before = count($this->kinds($ticket));

        $this->update($ticket, ['priority' => 'urgent']);

        // A no-op event is noise in a store nobody is allowed to prune.
        $this->assertCount($before, $this->kinds($ticket));
    }

    public function test_two_changes_in_one_request_record_two_events(): void
    {
        $ticket = $this->makeTicket();

        $this->update($ticket, ['priority' => 'urgent', 'status' => 'pending']);

        /*
         * "Priority changed" and "status changed" are two facts a reader
         * filters on separately; one combined row would be unfilterable.
         */
        $kinds = $this->kinds($ticket);

        $this->assertContains(TicketEventKind::PriorityChanged->value, $kinds);
        $this->assertContains(TicketEventKind::StatusChanged->value, $kinds);
    }

    public function test_the_version_after_matches_the_ticket(): void
    {
        $ticket = $this->makeTicket();

        $updated = $this->update($ticket, ['priority' => 'high']);

        $event = TicketEvent::query()
            ->where('event_type', TicketEventKind::PriorityChanged->value)
            ->firstOrFail();

        // Replaying the events has to reproduce the version sequence exactly,
        // or a disputed change cannot be placed in time.
        $this->assertSame($updated->version, $event->version_after);
    }

    public function test_a_failed_command_leaves_no_event_behind(): void
    {
        $ticket = $this->makeTicket();
        $before = count($this->kinds($ticket));

        try {
            // A stale version: the guard refuses, and the whole transaction —
            // ticket write and event alike — must come back.
            $this->update($ticket, ['priority' => 'urgent'], version: 99);
        } catch (\Throwable) {
            // Expected.
        }

        $this->assertCount($before, $this->kinds($ticket));
        $this->assertSame('normal', $ticket->refresh()->priority->value);
    }
}
