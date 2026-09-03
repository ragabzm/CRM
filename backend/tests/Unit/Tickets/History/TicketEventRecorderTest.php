<?php

declare(strict_types=1);

namespace Tests\Unit\Tickets\History;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\MakesTickets;
use Tests\TestCase;

final class TicketEventRecorderTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private function recorder(): TicketEventRecorder
    {
        return $this->app->make(TicketEventRecorder::class);
    }

    public function test_it_refuses_to_record_outside_a_transaction(): void
    {
        /*
         * The load-bearing test. Nothing stops a future command from writing
         * the ticket, returning, and recording afterwards — except that doing
         * so throws here. Without the refusal, a ticket write that succeeded
         * while its event failed would leave exactly the silent gap this store
         * exists to prevent.
         *
         * Against a stub connection, NOT the real one: `RefreshDatabase` wraps
         * every test in a transaction, so the real connection reports level 1
         * before the test has done anything and the guard could never fire. A
         * test that cannot fail is not a test — which is also why no other test
         * in this suite proves anything about this rule.
         */
        $outsideAnyTransaction = new class extends Connection
        {
            public function __construct() {}

            public function transactionLevel(): int
            {
                return 0;
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must be called inside a transaction');

        (new TicketEventRecorder($outsideAnyTransaction))->record(
            '01AAAAAAAAAAAAAAAAAAAAAAAA',
            TicketEventKind::Created,
            Actor::system('test'),
            null,
            ['status' => 'open'],
        );
    }

    public function test_the_guard_reads_the_real_connection(): void
    {
        // Paired with the test above: that one proves the rule, this one proves
        // the recorder is wired to something that can report a level at all.
        $this->assertGreaterThan(
            0,
            $this->app->make(ConnectionInterface::class)->transactionLevel(),
            'RefreshDatabase should have opened a transaction.',
        );
    }

    public function test_it_records_inside_a_transaction(): void
    {
        $ticketId = $this->ticket();

        DB::transaction(function () use ($ticketId): void {
            $this->recorder()->record(
                $ticketId,
                TicketEventKind::StatusChanged,
                Actor::system('auto_close'),
                ['status' => 'resolved'],
                ['status' => 'closed'],
                versionAfter: 3,
            );
        });

        $event = TicketEvent::query()->firstOrFail();

        $this->assertSame(TicketEventKind::StatusChanged->value, $event->event_type);
        $this->assertSame(['status' => 'resolved'], $event->payload['before']);
        $this->assertSame(['status' => 'closed'], $event->payload['after']);
        $this->assertNull($event->payload['meta']);
        $this->assertSame(3, $event->version_after);
    }

    public function test_a_recorded_event_carries_a_ulid_and_a_utc_timestamp(): void
    {
        $ticketId = $this->ticket();

        DB::transaction(function () use ($ticketId): void {
            $this->recorder()->record(
                $ticketId,
                TicketEventKind::Created,
                Actor::system('seed'),
                null,
                ['status' => 'open'],
            );
        });

        $event = TicketEvent::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $event->getKey());
        $this->assertSame('UTC', $event->created_at?->timezone->getName());
    }

    public function test_a_system_actor_records_its_reason_and_no_person(): void
    {
        $ticketId = $this->ticket();

        DB::transaction(function () use ($ticketId): void {
            $this->recorder()->record(
                $ticketId,
                TicketEventKind::StatusChanged,
                Actor::system('auto_close'),
                ['status' => 'resolved'],
                ['status' => 'closed'],
            );
        });

        $event = TicketEvent::query()->firstOrFail();

        // Never a person's id. Attributing an automatic decision to whoever
        // happened to trigger the job puts a name against a choice they did
        // not make.
        $this->assertSame('system', $event->actor_type);
        $this->assertNull($event->actor_id);
        $this->assertSame('auto_close', $event->actor_reason);
    }

    public function test_meta_carries_the_detail_specific_to_a_kind(): void
    {
        $ticketId = $this->ticket();

        DB::transaction(function () use ($ticketId): void {
            $this->recorder()->record(
                $ticketId,
                TicketEventKind::Resolved,
                Actor::system('test'),
                ['status' => 'open'],
                ['status' => 'resolved'],
                ['resolution_note' => 'Refunded.'],
            );
        });

        $this->assertSame(
            ['resolution_note' => 'Refunded.'],
            TicketEvent::query()->firstOrFail()->payload['meta'],
        );
    }

    public function test_the_payload_shape_is_the_same_for_every_kind(): void
    {
        $ticketId = $this->ticket();

        DB::transaction(function () use ($ticketId): void {
            foreach ([TicketEventKind::Created, TicketEventKind::Reopened] as $kind) {
                $this->recorder()->record($ticketId, $kind, Actor::system('t'), null, null);
            }
        });

        /*
         * Every event answers the same three questions in the same three
         * places. The shape used to vary by kind, which meant the panel reading
         * this store had to know what it was looking at before it could find
         * the values.
         */
        foreach (TicketEvent::query()->get() as $event) {
            $this->assertSame(['before', 'after', 'meta'], array_keys($event->payload));
        }
    }

    public function test_diff_returns_only_the_fields_that_moved(): void
    {
        $diff = TicketEventRecorder::diffChangedFields(
            ['status' => 'open', 'priority' => 'normal', 'assignee_id' => null],
            ['status' => 'pending', 'priority' => 'normal', 'assignee_id' => null],
            ['status', 'priority', 'assignee_id'],
        );

        // Not the whole row: a history that repeats unchanged values makes the
        // reader diff two blobs in their head to find the one that moved.
        $this->assertSame(['status' => 'open'], $diff['before']);
        $this->assertSame(['status' => 'pending'], $diff['after']);
    }

    public function test_diff_is_empty_when_nothing_moved(): void
    {
        $diff = TicketEventRecorder::diffChangedFields(
            ['status' => 'open'],
            ['status' => 'open'],
            ['status'],
        );

        // The signal a command uses to skip a no-op event entirely.
        $this->assertSame([], $diff['before']);
        $this->assertSame([], $diff['after']);
    }

    public function test_diff_distinguishes_null_from_a_falsy_value(): void
    {
        $diff = TicketEventRecorder::diffChangedFields(
            ['assignee_id' => null],
            ['assignee_id' => 0],
            ['assignee_id'],
        );

        // "Unassigned" and "assigned to user 0" are different facts, and loose
        // comparison alone would call them the same.
        $this->assertArrayHasKey('assignee_id', $diff['after']);
    }

    public function test_diff_ignores_keys_it_was_not_asked_about(): void
    {
        $diff = TicketEventRecorder::diffChangedFields(
            ['status' => 'open', 'subject' => 'A'],
            ['status' => 'open', 'subject' => 'B'],
            ['status'],
        );

        $this->assertSame([], $diff['after']);
    }

    /** A real ticket row, because ticket_id is a foreign key. */
    private function ticket(): string
    {
        return (string) $this->makeTicket()->getKey();
    }
}
