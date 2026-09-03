<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Replying is append-only and NOT version-guarded.
 *
 * Two colleagues writing different replies have not conflicted — they have both
 * said something, and both belong in the thread. Requiring a version here would
 * make a reply fail whenever someone else happened to change the priority a
 * moment earlier, which on a busy ticket is most of the time.
 */
final class AppendMessageTest extends TestCase
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

    private function reply(array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->postJson(
            "/api/v1/tickets/{$this->ticket['id']}/messages",
            ['body' => 'Thanks — looking into it now.', ...$body],
        );
    }

    public function test_a_reply_is_appended(): void
    {
        $this->reply()->assertStatus(201)
            ->assertJsonPath('direction', 'outbound')
            ->assertJsonPath('body', 'Thanks — looking into it now.');

        $this->assertDatabaseCount('ticket_messages', 1);
    }

    public function test_a_reply_needs_no_version(): void
    {
        // No `version` key anywhere in the request, and it succeeds.
        $this->reply()->assertStatus(201);
    }

    public function test_a_reply_succeeds_even_when_the_ticket_moved_on(): void
    {
        // Someone else changes a contended property first...
        $this->withIdempotencyKey()->patchJson("/api/v1/tickets/{$this->ticket['id']}", [
            'version' => 1,
            'priority' => 'urgent',
        ])->assertOk();

        // ...and the reply, written against the old state, still goes through.
        // This is the case the narrow guard exists to allow.
        $this->reply()->assertStatus(201);
    }

    public function test_two_replies_race_without_either_being_lost(): void
    {
        $this->reply(['body' => 'First reply'])->assertStatus(201);
        $this->reply(['body' => 'Second reply'])->assertStatus(201);

        $bodies = TicketMessage::query()->orderBy('sent_at')->orderBy('id')->pluck('body')->all();

        // Both kept. Neither overwrote the other, because neither was a change
        // to the same thing.
        $this->assertSame(['First reply', 'Second reply'], $bodies);
    }

    public function test_appending_does_not_disturb_the_ticket_version(): void
    {
        $before = Ticket::query()->findOrFail($this->ticket['id'])->version;

        $this->reply()->assertStatus(201);

        // Bumping it would make everyone else's open edit form stale for no
        // reason.
        $this->assertSame($before, Ticket::query()->findOrFail($this->ticket['id'])->version);
    }

    public function test_appending_records_that_a_reply_happened(): void
    {
        $before = \App\Modules\Tickets\Domain\TicketEvent::query()->count();

        $this->reply()->assertStatus(201);

        /*
         * REVERSED from Story 4.1, deliberately.
         *
         * This test used to assert the opposite — that appending wrote no event
         * — on the reasoning that "the thread IS the record; a parallel event
         * row would be the same fact stored twice." That objection was about
         * copying the BODY, and it was right: a message corrected at source
         * with a copy in an append-only store that can never be corrected is
         * two versions of what someone said.
         *
         * The event written here carries no body. It carries a message id and
         * a count, which makes it a pointer, not a copy. What it buys is the
         * thing the history panel exists for: "assigned to Dana, replied,
         * priority raised, replied again" read in one order. A history that
         * omits every reply cannot answer "what happened to this ticket".
         */
        $event = \App\Modules\Tickets\Domain\TicketEvent::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();

        $this->assertSame($before + 1, \App\Modules\Tickets\Domain\TicketEvent::query()->count());
        $this->assertSame('ticket.message_sent', $event->event_type);
        $this->assertArrayHasKey('message_id', $event->payload['meta']);
    }

    public function test_the_history_entry_does_not_copy_the_message_body(): void
    {
        $this->reply()->assertStatus(201);

        $event = \App\Modules\Tickets\Domain\TicketEvent::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();

        // The body lives on the message and nowhere else. Duplicating it here
        // would put an uncorrectable copy of someone's words in a store that by
        // design can never be edited.
        $this->assertNull($event->payload['before']);
        $this->assertNull($event->payload['after']);
        $this->assertArrayNotHasKey('body', $event->payload['meta']);
    }

    public function test_appending_still_does_not_bump_the_version(): void
    {
        $before = Ticket::query()->findOrFail($this->ticket['id'])->version;

        $this->reply()->assertStatus(201);

        // The history entry records the version it observed; it does not move
        // it. An append is not a change to contended state.
        $this->assertSame($before, Ticket::query()->findOrFail($this->ticket['id'])->version);
    }

    public function test_a_reply_records_who_wrote_it(): void
    {
        $author = $this->actingAsRole(Roles::SUPERVISOR, 'Hana Yousef');

        $this->reply()->assertStatus(201)->assertJsonPath('author.name', 'Hana Yousef');

        $message = TicketMessage::query()->firstOrFail();
        $this->assertSame('staff', $message->author_type);
        $this->assertSame((string) $author->getKey(), $message->author_id);
    }

    public function test_the_customer_is_carried_from_the_ticket(): void
    {
        $this->reply()->assertStatus(201);

        // Denormalised so the customer's whole history is one indexed read
        // rather than a join through every ticket they ever had.
        $this->assertSame($this->customerId, (string) TicketMessage::query()->firstOrFail()->customer_id);
    }

    public function test_an_inbound_message_can_be_logged(): void
    {
        $this->reply(['direction' => 'inbound'])->assertStatus(201)
            ->assertJsonPath('direction', 'inbound');
    }

    public function test_an_empty_reply_is_refused(): void
    {
        $this->reply(['body' => '   '])->assertStatus(422);

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    public function test_a_reply_to_a_missing_ticket_is_a_404(): void
    {
        $this->withIdempotencyKey()
            ->postJson('/api/v1/tickets/01JZZZZZZZZZZZZZZZZZZZZZZZ/messages', ['body' => 'Hello'])
            ->assertStatus(404);
    }

    public function test_the_thread_reads_oldest_first(): void
    {
        $this->reply(['body' => 'First'])->assertStatus(201);
        $this->travel(1)->minutes();
        $this->reply(['body' => 'Second'])->assertStatus(201);

        $bodies = array_column(
            $this->getJson("/api/v1/tickets/{$this->ticket['id']}/messages")->assertOk()->json('data'),
            'body',
        );

        // A thread is read top to bottom, unlike a timeline.
        $this->assertSame(['First', 'Second'], $bodies);
    }

    public function test_a_long_body_previews_without_cutting_mid_word(): void
    {
        $preview = TicketMessage::preview(str_repeat('alpha beta ', 40));

        $this->assertLessThanOrEqual(TicketMessage::PREVIEW_LENGTH + 1, mb_strlen($preview));
        $this->assertStringEndsWith('…', $preview);
        // Cutting mid-word reads as corruption rather than as truncation.
        $this->assertStringNotContainsString('alph…', $preview);
    }

    public function test_a_short_body_is_not_truncated(): void
    {
        $this->assertSame('Thanks for your help.', TicketMessage::preview('Thanks for your help.'));
    }
}
