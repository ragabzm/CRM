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

    /**
     * The whole graph, allowed and forbidden alike.
     *
     * Story 4.2 replaced 4.1's table: `closed` is no longer terminal — it
     * reopens inside the configured window — and the fifth `reopened` state is
     * gone, because a reopened ticket IS open and every "needs attention"
     * filter would otherwise have to remember a fifth value.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function transitions(): array
    {
        return [
            'open to pending' => ['open', 'pending', true],
            'open to resolved' => ['open', 'resolved', true],
            'pending to open' => ['pending', 'open', true],
            'pending to resolved' => ['pending', 'resolved', true],
            'resolved to closed' => ['resolved', 'closed', true],
            // The customer disagreed that it was done.
            'resolved to open' => ['resolved', 'open', true],
            // Reopened by hand, inside the window.
            'closed to open' => ['closed', 'open', true],

            // Closing skips the resolution nobody wrote down.
            'open to closed' => ['open', 'closed', false],
            'pending to closed' => ['pending', 'closed', false],
            // A closed ticket comes back to open and starts again, or stays
            // closed; re-entering mid-lifecycle would produce tickets that were
            // never open in their own history.
            'closed to pending' => ['closed', 'pending', false],
            'closed to resolved' => ['closed', 'resolved', false],
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

        $response->assertStatus(409)->assertJsonPath('code', 'tickets.transition_forbidden');
        $this->assertSame($from, Ticket::query()->findOrFail($this->id())->status->value);
    }

    public function test_the_refusal_names_the_edge_that_was_refused(): void
    {
        Ticket::query()->whereKey($this->id())->update(['status' => 'open']);

        $response = $this->moveTo('closed')->assertStatus(409);

        // Not a generic "invalid": the agent is told which move, and where the
        // ticket can actually go.
        $this->assertStringContainsString('cannot become closed', (string) $response->json('detail'));
        $this->assertSame(['pending', 'resolved'], $response->json('allowed'));
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
            $event->payload['meta']['resolution_note'],
        );
    }

    public function test_resolving_without_a_note_is_refused(): void
    {
        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/resolve", [
            'version' => $this->currentVersion(),
        ])->assertStatus(422);

        $this->assertSame('open', Ticket::query()->findOrFail($this->id())->status->value);
    }

    public function test_reopening_a_resolved_ticket_returns_it_to_open(): void
    {
        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/resolve", [
            'version' => $this->currentVersion(),
            'resolution_note' => 'Fixed.',
        ])->assertOk();

        $this->withIdempotencyKey()->postJson("/api/v1/tickets/{$this->id()}/reopen", [
            'version' => $this->currentVersion(),
            'reason' => 'Customer says it is still wrong.',
        ])->assertOk()->assertJsonPath('status', TicketStatus::Open->value);

        // That it came back is a fact about the HISTORY, which the event
        // records exactly — not a fifth status every filter has to remember.
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

    public function test_there_are_exactly_four_statuses(): void
    {
        /*
         * No `new`: a ticket is born open, and a state meaning "not looked at
         * yet" is answered by the assignee being null.
         *
         * No `cancelled`: a ticket raised in error is closed with a reason,
         * like any other ending. A second terminal state would double every
         * "is this finished?" check.
         *
         * No `reopened`: a reopened ticket IS open, and the fact that it came
         * back lives in the history where it belongs.
         */
        $this->assertSame(['open', 'pending', 'resolved', 'closed'], TicketStatus::values());
    }

    public function test_no_forbidden_status_appears_anywhere(): void
    {
        $offenders = [];

        foreach (['new', 'cancelled', 'canceled', 'reopened'] as $word) {
            foreach (\Tests\Architecture\SourceScanner::phpFiles('app/Modules/Tickets') as $file) {
                /*
                 * Migrations are exempt. One of them exists precisely to REMOVE
                 * `reopened` — from the rows and from the check constraint —
                 * and naming the value it deletes is the opposite of a
                 * violation. What the database will accept is asserted by that
                 * constraint itself.
                 */
                if (str_contains($file, '/Database/Migrations/')) {
                    continue;
                }

                $code = \Tests\Architecture\SourceScanner::codeOnly($file);

                // As a status literal specifically, not as an ordinary word.
                if (preg_match("/'{$word}'\\s*(=>|,|\\))/i", $code) === 1
                    && str_contains($code, 'status')) {
                    $offenders[] = basename($file)." mentions '{$word}'";
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_the_contract_publishes_only_the_four(): void
    {
        $spec = (string) file_get_contents(base_path('openapi.yaml'));

        foreach (['cancelled', 'reopened'] as $word) {
            // A contract advertising a status the server cannot produce would
            // have every client writing a branch that never runs.
            $this->assertStringNotContainsString("- {$word}", $spec);
        }
    }

    public function test_a_ticket_is_born_open(): void
    {
        $created = $this->createTicket();

        $this->assertSame(TicketStatus::Open->value, $created['status']);
    }

    public function test_a_ticket_cannot_be_created_in_another_status(): void
    {
        // `status` is not an accepted field on create; supplying one is
        // ignored rather than honoured.
        $created = $this->createTicket(['status' => 'closed']);

        $this->assertSame(TicketStatus::Open->value, $created['status']);
    }
}
