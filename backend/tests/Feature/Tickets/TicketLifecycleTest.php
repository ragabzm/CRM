<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class TicketLifecycleTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    private array $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTickets(Roles::SUPERVISOR);
        $this->ticket = $this->createTicket();
    }

    private function id(): string
    {
        return (string) $this->ticket['id'];
    }

    private function currentVersion(): int
    {
        return (int) Ticket::query()->findOrFail($this->id())->version;
    }

    private function moveTo(string $status): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->patchJson("/api/v1/tickets/{$this->id()}", [
            'version' => $this->currentVersion(),
            'status' => $status,
        ]);
    }

    /** @return array<string, array{string, string, bool}> */
    public static function transitions(): array
    {
        return [
            'open to pending' => ['open', 'pending', true],
            'open to resolved' => ['open', 'resolved', true],
            'open to closed' => ['open', 'closed', true],
            'pending to open' => ['pending', 'open', true],
            'pending to resolved' => ['pending', 'resolved', true],
            'resolved to reopened' => ['resolved', 'reopened', true],
            'resolved to closed' => ['resolved', 'closed', true],
            'reopened to resolved' => ['reopened', 'resolved', true],
            // The one terminal state.
            'closed to open' => ['closed', 'open', false],
            'closed to pending' => ['closed', 'pending', false],
            'closed to resolved' => ['closed', 'resolved', false],
            'closed to reopened' => ['closed', 'reopened', false],
            // Nonsense jumps.
            'open to reopened' => ['open', 'reopened', false],
            'pending to reopened' => ['pending', 'reopened', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('transitions')]
    public function test_the_lifecycle_table_is_enforced(string $from, string $to, bool $allowed): void
    {
        // Put the ticket into the starting state directly: the point of this
        // test is the transition rule, not the route that reaches it.
        Ticket::query()->whereKey($this->id())->update(['status' => $from]);

        $response = $this->moveTo($to);

        if ($allowed) {
            $response->assertOk()->assertJsonPath('status', $to);

            return;
        }

        $response->assertStatus(422)->assertJsonPath('code', 'tickets.lifecycle_violation');
        $this->assertSame($from, Ticket::query()->findOrFail($this->id())->status->value);
    }

    public function test_a_closed_ticket_says_what_to_do_instead(): void
    {
        Ticket::query()->whereKey($this->id())->update(['status' => 'closed']);

        $detail = (string) $this->moveTo('open')->assertStatus(422)->json('detail');

        // A refusal that only says no leaves the agent stuck with a customer
        // waiting.
        $this->assertStringContainsString('open a new one', $detail);
    }

    public function test_the_refusal_lists_where_the_ticket_can_go(): void
    {
        Ticket::query()->whereKey($this->id())->update(['status' => 'open']);

        $response = $this->moveTo('reopened')->assertStatus(422);

        $this->assertSame(['pending', 'resolved', 'closed'], $response->json('allowed'));
    }

    public function test_setting_a_status_to_what_it_already_is_succeeds(): void
    {
        // Otherwise a retried request would fail, which is the opposite of
        // what a retry should do.
        $this->moveTo('open')->assertOk()->assertJsonPath('status', 'open');
    }

    public function test_resolving_records_what_was_done(): void
    {
        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/resolve", [
            'version' => $this->currentVersion(),
            'resolution_note' => 'Credited the duplicate seat and reissued the invoice.',
        ])->assertOk()->assertJsonPath('status', 'resolved');

        $event = TicketEvent::query()->where('event_type', TicketEvent::RESOLVED)->firstOrFail();

        // The customer sees this, and so does whoever picks it up if it comes
        // back. A resolution nobody wrote down has to be reconstructed.
        $this->assertSame(
            'Credited the duplicate seat and reissued the invoice.',
            $event->payload['resolution_note'],
        );
    }

    public function test_resolving_without_a_note_is_refused(): void
    {
        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/resolve", [
            'version' => $this->currentVersion(),
        ])->assertStatus(422);

        $this->assertSame('open', Ticket::query()->findOrFail($this->id())->status->value);
    }

    public function test_reopening_is_distinct_from_open(): void
    {
        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/resolve", [
            'version' => $this->currentVersion(),
            'resolution_note' => 'Fixed.',
        ])->assertOk();

        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/reopen", [
            'version' => $this->currentVersion(),
            'reason' => 'Customer says it is still wrong.',
        ])->assertOk()->assertJsonPath('status', 'reopened');

        // Not "open": a reopened ticket is one where the first attempt did not
        // work, and collapsing the two loses the only signal that says so.
        $this->assertNotSame(TicketStatus::Open->value, Ticket::query()->findOrFail($this->id())->status->value);
        $this->assertDatabaseHas('ticket_events', ['event_type' => TicketEvent::REOPENED]);
    }

    public function test_a_ticket_is_assigned_and_returned_to_the_pool(): void
    {
        $agent = $this->actingAsRole(Roles::SUPERVISOR);

        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/assign", [
            'version' => $this->currentVersion(),
            'assignee_id' => $agent->getKey(),
        ])->assertOk()->assertJsonPath('assignee_id', $agent->getKey());

        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/assign", [
            'version' => $this->currentVersion(),
            'assignee_id' => null,
        ])->assertOk()->assertJsonPath('assignee_id', null);

        // Unassigning is a real instruction, not the absence of one.
        $this->assertDatabaseHas('ticket_events', ['event_type' => TicketEvent::ASSIGNEE_CHANGED]);
    }

    public function test_assignment_is_version_guarded_too(): void
    {
        $agent = $this->actingAsRole(Roles::SUPERVISOR);

        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/assign", [
            'version' => 999,
            'assignee_id' => $agent->getKey(),
        ])->assertStatus(409)->assertJsonPath('code', 'tickets.stale_version');
    }

    public function test_the_history_reads_in_order(): void
    {
        $this->moveTo('pending')->assertOk();
        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/resolve", [
            'version' => $this->currentVersion(),
            'resolution_note' => 'Done.',
        ])->assertOk();

        $versions = TicketEvent::query()
            ->where('ticket_id', $this->id())
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('version_after')
            ->all();

        // Replaying the events reproduces the version history exactly, which is
        // what makes a disputed change reconstructable.
        $this->assertSame([1, 2, 3], $versions);
    }
}
