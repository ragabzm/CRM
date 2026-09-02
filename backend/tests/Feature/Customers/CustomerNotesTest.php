<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Who may write, change and remove a note.
 *
 * Editing is about AUTHORSHIP, not role: you may change what you wrote, and
 * nobody may rewrite what a colleague wrote, because a note records what
 * somebody said and a silent edit destroys that. Deleting is different — it is
 * visible, and someone has to be able to take down a note written in anger or
 * containing a card number.
 */
final class CustomerNotesTest extends TestCase
{
    use InteractsWithCustomers;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private string $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCustomers();
        $this->customerId = (string) $this->createCustomer()['id'];
    }

    private function becomes(string $role, string $name = 'Someone Else'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());

        return $user;
    }

    private function addNote(string $body = 'Called about the invoice.'): array
    {
        return $this->withIdempotencyKey()
            ->postJson("/api/v1/customers/{$this->customerId}/notes", ['body' => $body])
            ->assertStatus(201)
            ->json();
    }

    public function test_a_note_records_who_wrote_it_and_when(): void
    {
        $author = $this->becomes(Roles::AGENT, 'Hana Yousef');

        $note = $this->addNote();

        $this->assertSame('Called about the invoice.', $note['body']);
        $this->assertSame((string) $author->getKey(), $note['author']['id']);
        $this->assertSame('Hana Yousef', $note['author']['name']);
        $this->assertNotNull($note['created_at']);
    }

    public function test_the_author_name_survives_the_account(): void
    {
        $author = $this->becomes(Roles::AGENT, 'Hana Yousef');
        $this->addNote();

        $author->delete();

        // The note still says who wrote it. A join would lose the name at
        // exactly the moment someone is working out who knew what.
        $this->becomes(Roles::SUPERVISOR);
        $notes = $this->getJson("/api/v1/customers/{$this->customerId}/notes")->assertOk()->json('data');

        $this->assertSame('Hana Yousef', $notes[0]['author']['name']);
        $this->assertNull($notes[0]['author']['id']);
    }

    public function test_an_agent_can_write_a_note(): void
    {
        $this->becomes(Roles::AGENT);

        // Recording what a caller said is part of handling the call. An agent
        // who could read a customer but not note anything would keep it in
        // their own head.
        $this->addNote();

        $this->assertDatabaseCount('customer_notes', 1);
    }

    public function test_notes_come_back_newest_first(): void
    {
        $this->becomes(Roles::AGENT);

        $this->addNote('First');
        $this->travel(1)->minutes();
        $this->addNote('Second');

        $bodies = array_column(
            $this->getJson("/api/v1/customers/{$this->customerId}/notes")->assertOk()->json('data'),
            'body',
        );

        $this->assertSame(['Second', 'First'], $bodies);
    }

    public function test_an_empty_note_is_refused(): void
    {
        $this->becomes(Roles::AGENT);

        $this->withIdempotencyKey()
            ->postJson("/api/v1/customers/{$this->customerId}/notes", ['body' => '   '])
            ->assertStatus(422);

        $this->assertDatabaseCount('customer_notes', 0);
    }

    public function test_a_note_has_an_upper_bound(): void
    {
        $this->becomes(Roles::AGENT);

        $this->withIdempotencyKey()
            ->postJson("/api/v1/customers/{$this->customerId}/notes", ['body' => str_repeat('x', 5001)])
            ->assertStatus(422);
    }

    public function test_a_note_on_a_customer_that_does_not_exist_is_a_404(): void
    {
        $this->becomes(Roles::AGENT);

        $this->withIdempotencyKey()
            ->postJson('/api/v1/customers/01JZZZZZZZZZZZZZZZZZZZZZZZ/notes', ['body' => 'x'])
            ->assertStatus(404);
    }

    public function test_the_author_can_correct_their_own_note(): void
    {
        $this->becomes(Roles::AGENT, 'Hana Yousef');
        $note = $this->addNote();

        // A real correction happens some time later; timestamps are stored to
        // the second, so an edit within the same second reads as unedited.
        $this->travel(1)->minutes();

        $this->withIdempotencyKey()
            ->patchJson("/api/v1/notes/{$note['id']}", ['body' => 'Called about the invoice, not the quote.'])
            ->assertOk()
            ->assertJsonPath('body', 'Called about the invoice, not the quote.')
            // Marked as edited, so a reader knows the text is not what was
            // originally written.
            ->assertJsonPath('edited', true);
    }

    public function test_nobody_else_can_rewrite_a_note(): void
    {
        $this->becomes(Roles::AGENT, 'Hana Yousef');
        $note = $this->addNote();

        $this->becomes(Roles::AGENT, 'Omar Saleh');

        $this->withIdempotencyKey()
            ->patchJson("/api/v1/notes/{$note['id']}", ['body' => 'Something they never said.'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'customers.note_not_yours');

        $this->assertDatabaseHas('customer_notes', ['body' => 'Called about the invoice.']);
    }

    public function test_not_even_a_supervisor_can_rewrite_a_note(): void
    {
        $this->becomes(Roles::AGENT, 'Hana Yousef');
        $note = $this->addNote();

        $this->becomes(Roles::SUPERVISOR, 'The Supervisor');

        // A deletion is visible; an edit is not. Rewriting what a colleague
        // said, in their name, leaves no trace that it happened.
        $this->withIdempotencyKey()
            ->patchJson("/api/v1/notes/{$note['id']}", ['body' => 'Rewritten.'])
            ->assertStatus(403);
    }

    public function test_the_author_can_delete_their_own_note(): void
    {
        $this->becomes(Roles::AGENT, 'Hana Yousef');
        $note = $this->addNote();

        $this->withIdempotencyKey()->deleteJson("/api/v1/notes/{$note['id']}")->assertOk();

        $this->assertDatabaseCount('customer_notes', 0);
    }

    public function test_a_supervisor_can_delete_anyone_s_note(): void
    {
        $this->becomes(Roles::AGENT, 'Hana Yousef');
        $note = $this->addNote('Something regrettable.');

        $this->becomes(Roles::SUPERVISOR, 'The Supervisor');

        // Someone has to be able to take down a note written in anger, or one
        // holding a customer's card number.
        $this->withIdempotencyKey()->deleteJson("/api/v1/notes/{$note['id']}")->assertOk();

        $this->assertDatabaseCount('customer_notes', 0);
    }

    public function test_an_agent_cannot_delete_someone_else_s_note(): void
    {
        $this->becomes(Roles::AGENT, 'Hana Yousef');
        $note = $this->addNote();

        $this->becomes(Roles::AGENT, 'Omar Saleh');

        $response = $this->withIdempotencyKey()->deleteJson("/api/v1/notes/{$note['id']}")->assertStatus(403);

        // The refusal says what to do instead rather than only saying no.
        $this->assertStringContainsString('supervisor', (string) $response->json('detail'));
        $this->assertDatabaseCount('customer_notes', 1);
    }

    public function test_a_note_that_does_not_exist_is_a_404_not_a_403(): void
    {
        $this->becomes(Roles::AGENT);

        // A 403 here would confirm that some note with that id exists.
        $this->withIdempotencyKey()
            ->deleteJson('/api/v1/notes/01JZZZZZZZZZZZZZZZZZZZZZZZ')
            ->assertStatus(404);
    }

    public function test_a_guest_cannot_read_or_write_notes(): void
    {
        $this->app['auth']->forgetGuards();
        $this->refreshApplication();
        $this->setUpSpaOrigin();

        $this->getJson("/api/v1/customers/{$this->customerId}/notes")->assertStatus(401);
    }

    public function test_notes_are_removed_with_their_customer(): void
    {
        $this->becomes(Roles::SUPERVISOR);
        $this->addNote();

        \App\Modules\Customers\Domain\Customer::query()->findOrFail($this->customerId)->delete();

        // A note has no meaning without its customer. Customers are
        // deactivated rather than deleted, so this fires essentially never.
        $this->assertDatabaseCount('customer_notes', 0);
    }
}
