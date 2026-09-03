<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The history cannot be edited, through any door.
 *
 * `save()` alone used to be the only guard, which left `update()`,
 * `updateOrFail()` and `forceDelete()` reaching the database directly. An
 * append-only store that can be edited through three of its four methods is a
 * convention, not a guarantee — and a history that settles a disagreement has
 * to be a guarantee.
 */
final class TicketEventsImmutableTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private function event(): TicketEvent
    {
        $ticket = $this->makeTicket();

        DB::transaction(function () use ($ticket): void {
            $this->app->make(TicketEventRecorder::class)->record(
                (string) $ticket->getKey(),
                TicketEventKind::Created,
                Actor::system('test'),
                null,
                ['status' => 'open'],
            );
        });

        return TicketEvent::query()->firstOrFail();
    }

    public function test_an_event_cannot_be_updated(): void
    {
        $this->expectException(LogicException::class);

        $this->event()->update(['event_type' => 'ticket.rewritten']);
    }

    public function test_an_event_cannot_be_updated_or_failed(): void
    {
        $this->expectException(LogicException::class);

        $this->event()->updateOrFail(['event_type' => 'ticket.rewritten']);
    }

    public function test_an_event_cannot_be_re_saved(): void
    {
        $event = $this->event();
        $event->event_type = 'ticket.rewritten';

        $this->expectException(LogicException::class);

        $event->save();
    }

    public function test_an_event_cannot_be_deleted(): void
    {
        $this->expectException(LogicException::class);

        $this->event()->delete();
    }

    public function test_an_event_cannot_be_force_deleted(): void
    {
        $this->expectException(LogicException::class);

        $this->event()->forceDelete();
    }

    public function test_a_refused_edit_leaves_the_row_exactly_as_it_was(): void
    {
        $event = $this->event();
        $original = $event->event_type;

        try {
            $event->update(['event_type' => 'ticket.rewritten']);
        } catch (LogicException) {
            // Expected.
        }

        // The throw is only worth anything if nothing reached the database
        // before it.
        $this->assertSame($original, TicketEvent::query()->firstOrFail()->event_type);
    }

    public function test_the_table_records_no_update_time(): void
    {
        // An `updated_at` column would be an invitation: something that records
        // when a row was last changed implies rows get changed.
        $this->assertFalse(
            \Schema::hasColumn('ticket_events', 'updated_at'),
            'ticket_events must not have an updated_at column.',
        );
        $this->assertFalse((new TicketEvent)->usesTimestamps());
    }

    public function test_the_table_has_no_soft_deletes(): void
    {
        $this->assertFalse(\Schema::hasColumn('ticket_events', 'deleted_at'));
    }
}
