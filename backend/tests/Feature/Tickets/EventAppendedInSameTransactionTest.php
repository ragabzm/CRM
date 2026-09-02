<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AssignTicket;
use App\Modules\Tickets\Domain\Commands\ChangeStatus;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Commands\ReopenTicket;
use App\Modules\Tickets\Domain\Commands\ResolveTicket;
use App\Modules\Tickets\Domain\Commands\TicketAttributeChanges;
use App\Modules\Tickets\Domain\Commands\UpdateTicketAttributes;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Every command writes its event in the same transaction as its change.
 *
 * A change that committed without its event would leave a history with a hole
 * in it — and the hole would be exactly the change somebody is trying to
 * explain. There is no later moment at which that could be repaired.
 */
final class EventAppendedInSameTransactionTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    private Actor $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->setUpTickets(Roles::SUPERVISOR);
        $this->actor = Actor::staff((string) $user->getKey(), 'Hana Yousef');
    }

    private function newTicket(): Ticket
    {
        return $this->app->make(CreateTicket::class)->handle(
            $this->actor,
            new CreateTicketInput(
                subject: 'Invoice is wrong',
                description: 'Charged twice.',
                customerId: $this->customerId,
                channel: TicketChannel::Agent,
            ),
        );
    }

    /**
     * Runs the command and reports the statements it issued, in order.
     *
     * @return list<string>
     */
    private function statementsFor(callable $command): array
    {
        $log = [];

        DB::listen(function ($query) use (&$log): void {
            $log[] = $query->sql;
        });

        $command();

        DB::flushQueryLog();

        return $log;
    }

    /** @param list<string> $statements */
    private function assertWrittenTogether(array $statements, string $table): void
    {
        $writes = array_values(array_filter($statements, static fn (string $sql): bool => (
            str_contains($sql, 'insert into "ticket_events"')
            || str_contains($sql, "update \"{$table}\"")
            || str_contains($sql, "insert into \"{$table}\"")
        )));

        $this->assertNotEmpty($writes, "Expected a write to [{$table}].");

        $eventInserts = array_filter($writes, static fn (string $sql): bool => str_contains($sql, 'ticket_events'));

        // The event is there, alongside the change, in the same run.
        $this->assertNotEmpty($eventInserts, 'The command wrote no ticket_events row.');
    }

    public function test_creating_writes_its_event(): void
    {
        $statements = $this->statementsFor(fn () => $this->newTicket());

        $this->assertWrittenTogether($statements, 'tickets');
        $this->assertSame(1, TicketEvent::query()->where('event_type', TicketEvent::CREATED)->count());
    }

    public function test_updating_attributes_writes_its_event(): void
    {
        $ticket = $this->newTicket();

        $statements = $this->statementsFor(fn () => $this->app->make(UpdateTicketAttributes::class)->handle(
            $this->actor,
            (string) $ticket->getKey(),
            1,
            TicketAttributeChanges::of(['priority' => \App\Modules\Tickets\Domain\Priority::High]),
        ));

        $this->assertWrittenTogether($statements, 'tickets');
        $this->assertDatabaseHas('ticket_events', ['event_type' => TicketEvent::PRIORITY_CHANGED]);
    }

    public function test_assigning_writes_its_event(): void
    {
        $ticket = $this->newTicket();
        $agent = $this->actingAsRole(Roles::AGENT);

        $statements = $this->statementsFor(fn () => $this->app->make(AssignTicket::class)->handle(
            $this->actor,
            (string) $ticket->getKey(),
            1,
            (int) $agent->getKey(),
        ));

        $this->assertWrittenTogether($statements, 'tickets');
        $this->assertDatabaseHas('ticket_events', ['event_type' => TicketEvent::ASSIGNEE_CHANGED]);
    }

    public function test_changing_status_writes_its_event(): void
    {
        $ticket = $this->newTicket();

        $statements = $this->statementsFor(fn () => $this->app->make(ChangeStatus::class)->handle(
            $this->actor,
            (string) $ticket->getKey(),
            1,
            TicketStatus::Pending,
        ));

        $this->assertWrittenTogether($statements, 'tickets');
        $this->assertDatabaseHas('ticket_events', ['event_type' => TicketEvent::STATUS_CHANGED]);
    }

    public function test_resolving_writes_its_event(): void
    {
        $ticket = $this->newTicket();

        $statements = $this->statementsFor(fn () => $this->app->make(ResolveTicket::class)->handle(
            $this->actor,
            (string) $ticket->getKey(),
            1,
            'Credited the duplicate charge.',
        ));

        $this->assertWrittenTogether($statements, 'tickets');
        $this->assertDatabaseHas('ticket_events', ['event_type' => TicketEvent::RESOLVED]);
    }

    public function test_reopening_writes_its_event(): void
    {
        $ticket = $this->newTicket();
        $this->app->make(ResolveTicket::class)->handle($this->actor, (string) $ticket->getKey(), 1, 'Fixed.');

        $statements = $this->statementsFor(fn () => $this->app->make(ReopenTicket::class)->handle(
            $this->actor,
            (string) $ticket->getKey(),
            2,
            'Still wrong.',
        ));

        $this->assertWrittenTogether($statements, 'tickets');
        $this->assertDatabaseHas('ticket_events', ['event_type' => TicketEvent::REOPENED]);
    }

    public function test_a_failed_change_leaves_no_event_behind(): void
    {
        $ticket = $this->newTicket();
        $before = TicketEvent::query()->count();

        try {
            // A stale version: the guard throws inside the transaction.
            $this->app->make(UpdateTicketAttributes::class)->handle(
                $this->actor,
                (string) $ticket->getKey(),
                999,
                TicketAttributeChanges::of(['status' => TicketStatus::Resolved]),
            );
            $this->fail('Expected the stale version to be refused.');
        } catch (\App\Modules\Platform\Exceptions\ProblemException) {
            // expected
        }

        // Rolled back together: no orphan event claiming a change that never
        // happened.
        $this->assertSame($before, TicketEvent::query()->count());
        $this->assertSame(1, Ticket::query()->findOrFail($ticket->getKey())->version);
    }

    public function test_every_command_with_a_handle_is_covered_here(): void
    {
        $commands = [];

        foreach (glob(base_path('app/Modules/Tickets/Domain/Commands/*.php')) ?: [] as $file) {
            $source = (string) file_get_contents($file);

            if (str_contains($source, 'public function handle(')) {
                $commands[] = basename($file, '.php');
            }
        }

        sort($commands);

        /*
         * Pinned, so a seventh command cannot be added without either a test
         * here or a deliberate decision to change this list. AppendMessage is
         * listed and deliberately writes NO event — see its own suite.
         */
        $this->assertSame([
            'AppendMessage',
            'AssignTicket',
            'ChangeDepartment',
            'ChangeStatus',
            'CreateTicket',
            'ReopenTicket',
            'ResolveTicket',
            'UpdateTicketAttributes',
        ], $commands);
    }
}
