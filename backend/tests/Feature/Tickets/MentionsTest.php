<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Personal\NoteMention;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Notifications\MentionedInNote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Pulling a colleague into a ticket by name.
 *
 * A mention NOTIFIES AND DOES NOTHING ELSE. It does not subscribe anybody, add
 * them to a followers list, or grant visibility — department is not an access
 * boundary here, so there is nothing to widen. Every one of those is a feature
 * somebody expects at this point, and every one turns "look at this" into a
 * standing relationship nobody remembers agreeing to.
 *
 * The resolution is done on the SERVER, from the body that was stored. A
 * client-supplied list of mentioned users is a field anybody can edit into a
 * notification about a note that never named them.
 */
final class MentionsTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private User $author;

    private User $colleague;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->author = $this->makeUser(Roles::AGENT);
        $this->author->forceFill(['name' => 'Nadia Salem'])->save();

        $this->colleague = $this->makeUser(Roles::AGENT);
        $this->colleague->forceFill(['name' => 'Omar Fathy'])->save();

        $this->ticket = $this->makeTicket();
    }

    private function note(string $body, ?User $by = null): string
    {
        $actor = $by ?? $this->author;

        return (string) $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $actor->getKey(), (string) $actor->name),
            (string) $this->ticket->getKey(),
            MessageDirection::Internal,
            $body,
        )->getKey();
    }

    public function test_a_mention_in_an_internal_note_notifies_the_named_colleague(): void
    {
        Notification::fake();

        $this->note('@Omar Fathy can you sanity-check the timeline?');

        Notification::assertSentTo($this->colleague, MentionedInNote::class);
        $this->assertSame(1, NoteMention::query()->where('user_id', $this->colleague->getKey())->count());
    }

    public function test_a_mention_does_nothing_but_notify(): void
    {
        $before = $this->ticket->refresh()->only(['status', 'assignee_id', 'department_id', 'version']);

        $this->note('@Omar Fathy have a look');

        /*
         * No following, no followers list, no subscription, no visibility
         * grant, and not a scratch on the ticket itself.
         */
        $this->assertEquals($before, $this->ticket->refresh()->only(['status', 'assignee_id', 'department_id', 'version']));

        foreach (['ticket_followers', 'followers', 'subscriptions', 'ticket_watchers'] as $table) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Schema::hasTable($table),
                "`{$table}` has appeared — a mention is not a subscription.",
            );
        }
    }

    public function test_a_mention_in_a_customer_facing_reply_notifies_nobody(): void
    {
        Notification::fake();

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $this->author->getKey(), (string) $this->author->name),
            (string) $this->ticket->getKey(),
            // Outbound: the customer reads this.
            MessageDirection::Outbound,
            'Thanks @Omar Fathy will take a look.',
        );

        /*
         * An `@name` in a reply is text the CUSTOMER reads, not a way to pull
         * a colleague in. Notifying on it would tell a colleague about a
         * conversation they were never part of, and put their name in front of
         * the customer.
         */
        Notification::assertNothingSent();
        $this->assertSame(0, NoteMention::query()->count());
    }

    public function test_the_client_cannot_name_somebody_the_note_did_not(): void
    {
        Notification::fake();

        $this->actingAs($this->author, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/tickets/'.$this->ticket->getKey().'/messages', [
                'direction' => 'internal',
                'body' => 'Nothing to see here.',
                // The shape somebody would forge. Nothing reads it.
                'mentioned_user_ids' => [(int) $this->colleague->getKey()],
                'mentions' => [(int) $this->colleague->getKey()],
            ]);

        Notification::assertNothingSent();
        $this->assertSame(0, NoteMention::query()->count());
    }

    public function test_naming_yourself_does_not_notify_you(): void
    {
        Notification::fake();

        // People do it while writing — "@me: chase this". A system that emails
        // you your own words teaches you to ignore the one from somebody else.
        $this->note('@Nadia Salem remember to chase this');

        Notification::assertNothingSent();
        $this->assertSame(0, NoteMention::query()->count());
    }

    public function test_a_deactivated_colleague_is_refused_at_composition(): void
    {
        $this->colleague->forceFill(['is_active' => false])->save();

        $response = $this->actingAs($this->author, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/tickets/'.$this->ticket->getKey().'/messages', [
                'direction' => 'internal',
                'body' => '@Omar Fathy can you look?',
            ]);

        /*
         * Refused out loud, not dropped in silence. Silently ignoring it would
         * leave the writer believing they had pulled somebody in, and the note
         * would sit there naming a person who is never going to read it.
         */
        $response->assertStatus(422);
        $this->assertSame('tickets.mention_inactive', $response->json('code'));
        $this->assertStringContainsString('Omar Fathy', (string) $response->json('detail'));
    }

    public function test_the_picker_offers_active_colleagues_only(): void
    {
        $this->colleague->forceFill(['is_active' => false])->save();

        $names = array_column(
            $this->actingAs($this->author, 'web')->getJson('/api/v1/assignees')->json('data'),
            'name',
        );

        $this->assertNotContains('Omar Fathy', $names);
        $this->assertContains('Nadia Salem', $names);
    }

    public function test_a_longer_name_wins_over_a_shorter_one_inside_it(): void
    {
        Notification::fake();

        $shortName = $this->makeUser(Roles::AGENT);
        $shortName->forceFill(['name' => 'Omar'])->save();

        $this->note('@Omar Fathy please look');

        /*
         * "@Omar" is a legitimate whole-word match inside "@Omar Fathy" —
         * the character after it is a space. Without consuming the matched
         * span, every mention of Omar Fathy would also notify Omar, silently,
         * and only on desks that happen to employ both.
         */
        Notification::assertSentTo($this->colleague, MentionedInNote::class);
        Notification::assertNotSentTo($shortName, MentionedInNote::class);
    }

    public function test_a_name_that_is_only_a_prefix_is_not_a_mention(): void
    {
        Notification::fake();

        $this->colleague->forceFill(['name' => 'Ali'])->save();

        $this->note('@Alia Hassan asked about this');

        // Mentioning Alia must not notify Ali, every single time.
        Notification::assertNothingSent();
    }

    public function test_naming_the_same_person_twice_is_one_mention(): void
    {
        Notification::fake();

        $this->note('@Omar Fathy — and again, @Omar Fathy');

        $this->assertSame(1, NoteMention::query()->count());
        Notification::assertSentToTimes($this->colleague, MentionedInNote::class, 1);
    }

    public function test_the_mention_opens_the_ticket_at_the_note(): void
    {
        $messageId = $this->note('@Omar Fathy look here');

        $notification = new MentionedInNote(
            (string) $this->ticket->getKey(),
            (string) $this->ticket->reference,
            (string) $this->ticket->subject,
            'Nadia Salem',
            $messageId,
        );

        $mail = $notification->toMail($this->colleague);

        /*
         * At the NOTE, not the top of the thread. The whole content of a
         * mention is "come and read this sentence"; landing at the top of a
         * long conversation asks somebody to search for it.
         */
        $this->assertStringContainsString('#note-'.$messageId, (string) $mail->actionUrl);
        $this->assertSame($messageId, $notification->toArray($this->colleague)['message_id']);
    }

    public function test_the_home_tab_lists_the_notes_that_named_me(): void
    {
        $this->note('@Omar Fathy can you sanity-check the timeline?');

        $response = $this->actingAs($this->colleague, 'web')->getJson('/api/v1/me/mentions');

        $response->assertOk();
        $response->assertJsonPath('data.0.reference', $this->ticket->reference);
        $response->assertJsonPath('data.0.author_name', 'Nadia Salem');
        $response->assertJsonPath('data.0.read_at', null);
        $this->assertStringContainsString('sanity-check', $response->json('data.0.excerpt'));
    }

    public function test_i_never_see_a_mention_that_named_somebody_else(): void
    {
        $this->note('@Omar Fathy over here');

        $stranger = $this->makeUser(Roles::AGENT);

        $this->assertSame([], $this->actingAs($stranger, 'web')->getJson('/api/v1/me/mentions')->json('data'));
    }

    public function test_a_mention_can_be_marked_read_and_stops_counting(): void
    {
        $this->note('@Omar Fathy over here');

        $id = $this->actingAs($this->colleague, 'web')->getJson('/api/v1/me/mentions')->json('data.0.id');

        $this->assertSame(1, $this->badge());

        $this->actingAs($this->colleague, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/me/mentions/'.$id.'/read')
            ->assertOk();

        $this->assertSame(0, $this->badge());
        // Still listed. Read is not deleted — the note still named them.
        $this->assertCount(1, $this->actingAs($this->colleague, 'web')->getJson('/api/v1/me/mentions')->json('data'));
    }

    public function test_somebody_elses_mention_cannot_be_marked_read(): void
    {
        $this->note('@Omar Fathy over here');

        $id = (string) NoteMention::query()->value('id');
        $stranger = $this->makeUser(Roles::AGENT);

        $this->actingAs($stranger, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/me/mentions/'.$id.'/read')
            ->assertNotFound();

        $this->assertNull(NoteMention::query()->value('read_at'));
    }

    public function test_a_mention_is_never_visible_on_a_customer_surface(): void
    {
        $this->note('@Omar Fathy the customer must never read this');

        $this->app['auth']->forgetGuards();

        $portal = $this->getJson('/api/v1/portal/requests/'.$this->ticket->getKey());

        $this->assertStringNotContainsString('Omar Fathy', $portal->getContent());
        $this->assertStringNotContainsString('must never read this', $portal->getContent());
    }

    public function test_a_note_with_no_at_sign_costs_no_lookup(): void
    {
        DB::enableQueryLog();
        $this->note('A plain note with nothing in it.');
        $queries = DB::getRawQueryLog();
        DB::disableQueryLog();

        /*
         * The overwhelmingly common case, answered without touching the user
         * table. A mention parser that queries on every note is a query on
         * every note.
         */
        $touchedUsers = array_filter(
            $queries,
            static fn (array $q): bool => str_contains((string) $q['raw_query'], 'from "users"'),
        );

        $this->assertSame([], $touchedUsers);
    }

    private function badge(): int
    {
        return (int) $this->actingAs($this->colleague, 'web')
            ->getJson('/api/v1/tickets/counts')
            ->json('personal.mentions');
    }
}
