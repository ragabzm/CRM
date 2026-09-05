<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Infrastructure\NullMailTransport;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\ChangeStatus;
use App\Modules\Tickets\Domain\Commands\RateTicket;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Feedback\FeedbackInvitation;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * "How did it go?", asked once, answerable from the inbox.
 *
 * The portal already asks whoever signs in. Most customers never will — they
 * emailed a support address and that is the whole relationship — so an
 * invitation that only exists behind a login is an invitation most people
 * never receive.
 *
 * Three things are load-bearing and each has a test that fails without it: the
 * question is asked ONCE, the link is authorised by its SIGNATURE and nothing
 * else, and following it grants NOTHING but that one answer.
 */
final class FeedbackInvitationTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private NullMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->settings()->set('email.enabled', true, null);
        $this->settings()->set('email.acknowledgement.enabled', false, null);
        $this->settings()->set('email.feedback_invitation.enabled', true, null);

        $this->transport = $this->app->make(NullMailTransport::class);
        $this->app->instance(MailTransport::class, $this->transport);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    /** A ticket with a customer who has an email address on file. */
    private function openTicket(string $channel = 'email', ?string $locale = null): Ticket
    {
        $customerId = $this->makeCustomer();

        DB::table('customers')->where('id', $customerId)->update([
            'preferred_locale' => $locale,
            'full_name' => 'Hana Yousef',
        ]);

        DB::table('contact_identifiers')->insert([
            'id' => (string) Str::ulid(),
            'customer_id' => $customerId,
            'kind' => 'email',
            'value' => 'hana@example.test',
            'value_normalised' => 'hana@example.test',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->makeTicket(['customer_id' => $customerId, 'channel' => $channel]);
    }

    private function moveTo(Ticket $ticket, TicketStatus $status): Ticket
    {
        $agent = $this->makeUser(Roles::AGENT);

        return $this->app->make(ChangeStatus::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            // Staff are held to a version. Read it fresh, because the caller's
            // copy is stale the moment anything else has touched the ticket.
            $ticket->refresh()->version,
            $status,
        );
    }

    /** The two links out of the one email that was sent. */
    private function linksInTheEmail(): array
    {
        $body = (string) ($this->transport->lastSent()['body'] ?? '');

        preg_match_all('#https?://\S+/feedback/\S+#', $body, $matches);

        return $matches[0];
    }

    public function test_finishing_a_request_asks_the_customer_how_it_went(): void
    {
        $ticket = $this->openTicket();

        $this->moveTo($ticket, TicketStatus::Resolved);

        $this->assertCount(1, $this->transport->sent());
        $this->assertCount(2, $this->linksInTheEmail(), 'One link per answer, and no third.');
    }

    public function test_the_question_is_asked_once_and_never_chased(): void
    {
        $ticket = $this->openTicket();

        $this->moveTo($ticket, TicketStatus::Resolved);
        // Resolved, reopened, resolved again — the same conversation coming
        // back, which is common and is not a second thing to ask about.
        $this->moveTo($ticket, TicketStatus::Open);
        $this->moveTo($ticket, TicketStatus::Resolved);
        // And closed afterwards, which is a second finish by any reading.
        $this->moveTo($ticket, TicketStatus::Closed);

        $this->assertCount(1, $this->transport->sent(), 'The customer was asked more than once.');
    }

    public function test_the_first_finish_is_recorded_once_and_never_moves(): void
    {
        $ticket = $this->openTicket();

        $this->moveTo($ticket, TicketStatus::Resolved);
        $first = $ticket->refresh()->first_finished_at;

        $this->assertNotNull($first);

        $this->travel(2)->hours();
        $this->moveTo($ticket, TicketStatus::Open);
        $this->moveTo($ticket, TicketStatus::Resolved);

        $this->assertEquals($first, $ticket->refresh()->first_finished_at);
    }

    public function test_a_tap_in_the_email_records_the_answer(): void
    {
        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);

        [$up] = $this->linksInTheEmail();

        $response = $this->get($up);

        // Sent onwards to the page that offers the optional comment. The
        // rating is already recorded — the tap WAS the answer.
        $response->assertRedirect();
        $this->assertTrue($ticket->refresh()->satisfaction);
        $this->assertNull($ticket->refresh()->satisfaction_comment);
    }

    public function test_the_answer_is_recorded_as_the_customer_not_the_system(): void
    {
        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);

        [, $down] = $this->linksInTheEmail();
        $this->get($down);

        $event = DB::table('ticket_events')
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', TicketEventKind::Rated->value)
            ->first();

        $this->assertNotNull($event);
        /*
         * AC-10 asks for the actor. A rating attributed to `system` would make
         * the record say a machine decided how the customer felt — and the
         * customer id, not a portal account id, because this person may never
         * have registered.
         */
        $this->assertSame('customer', $event->actor_type);
        $this->assertSame((string) $ticket->customer_id, (string) $event->actor_id);
    }

    public function test_the_comment_is_offered_after_the_answer_and_posts_back(): void
    {
        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);

        [$up] = $this->linksInTheEmail();
        $this->get($up);

        $this->post($up, ['comment' => 'Sorted in an hour.'])->assertOk();

        $this->assertTrue($ticket->refresh()->satisfaction);
        $this->assertSame('Sorted in an hour.', $ticket->refresh()->satisfaction_comment);
    }

    public function test_tapping_the_same_link_again_does_not_erase_the_comment(): void
    {
        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);

        [$up] = $this->linksInTheEmail();
        $this->get($up);
        $this->post($up, ['comment' => 'Sorted in an hour.']);

        // Back to the email, tapped again. They meant "yes, that answer" —
        // not "delete what I wrote".
        $this->get($up);

        $this->assertSame('Sorted in an hour.', $ticket->refresh()->satisfaction_comment);
    }

    public function test_an_edited_link_is_refused(): void
    {
        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);

        [$up] = $this->linksInTheEmail();

        // The verdict flipped by hand in the address bar.
        $this->get(str_replace('/up?', '/down?', $up))->assertForbidden();

        $this->assertNull($ticket->refresh()->satisfaction);
    }

    public function test_a_guessed_ticket_id_is_refused(): void
    {
        $mine = $this->openTicket();
        $theirs = $this->openTicket();

        $this->moveTo($mine, TicketStatus::Resolved);
        [$up] = $this->linksInTheEmail();

        /*
         * The confinement is the signature. Swapping in a ticket id that is
         * real and finished still fails, because the id is inside what was
         * signed — there is nothing to guess.
         */
        $this->moveTo($theirs, TicketStatus::Resolved);

        $this->get(str_replace((string) $mine->getKey(), (string) $theirs->getKey(), $up))
            ->assertForbidden();

        $this->assertNull($theirs->refresh()->satisfaction);
    }

    public function test_the_link_expires_with_the_change_window(): void
    {
        $this->settings()->set(RateTicket::WINDOW_SETTING, 24, null);

        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);
        [$up] = $this->linksInTheEmail();

        $this->travel(25)->hours();

        $this->get($up)->assertForbidden();
        $this->assertNull($ticket->refresh()->satisfaction);
    }

    public function test_a_zero_change_window_still_ships_a_usable_link(): void
    {
        /*
         * Zero locks a rating the moment it is given — a rule about CHANGES.
         * Signing a link that expires immediately would post an invitation
         * nobody could ever accept.
         */
        $this->settings()->set(RateTicket::WINDOW_SETTING, 0, null);

        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);
        [$up] = $this->linksInTheEmail();

        $this->get($up)->assertRedirect();
        $this->assertTrue($ticket->refresh()->satisfaction);
    }

    public function test_a_whatsapp_customer_is_not_emailed_about_it(): void
    {
        $ticket = $this->openTicket(channel: 'whatsapp');

        $this->moveTo($ticket, TicketStatus::Resolved);

        // They asked on WhatsApp. The portal still asks; email does not.
        $this->assertSame([], $this->transport->sent());
    }

    public function test_the_invitation_can_be_turned_off(): void
    {
        $this->settings()->set('email.feedback_invitation.enabled', false, null);

        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);

        $this->assertSame([], $this->transport->sent());
    }

    public function test_a_customer_with_no_address_is_simply_not_emailed(): void
    {
        $ticket = $this->makeTicket(['channel' => 'email']);

        $this->moveTo($ticket, TicketStatus::Resolved);

        // Not an error: there is nothing to send to.
        $this->assertSame([], $this->transport->sent());
        $this->assertNotNull($ticket->refresh()->first_finished_at);
    }

    public function test_the_invitation_is_written_in_the_customers_language(): void
    {
        $ticket = $this->openTicket(locale: 'ar');
        $this->moveTo($ticket, TicketStatus::Resolved);

        $body = (string) $this->transport->lastSent()['body'];

        $this->assertStringContainsString(__('emails.feedback.good', [], 'ar'), $body);
        $this->assertStringNotContainsString(__('emails.feedback.good', [], 'en'), $body);
    }

    public function test_the_labels_are_words_rather_than_symbols(): void
    {
        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);

        $body = (string) $this->transport->lastSent()['body'];

        /*
         * A mail client that strips emoji, or renders it as a box, would leave
         * the customer choosing between two identical links.
         */
        $this->assertStringContainsString(__('emails.feedback.good', [], 'en'), $body);
        $this->assertStringContainsString(__('emails.feedback.bad', [], 'en'), $body);
        $this->assertDoesNotMatchRegularExpression('/[0-9]\s*(star|point|\/\s*5)/i', $body);
    }

    public function test_the_link_grants_nothing_beyond_the_rating(): void
    {
        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);
        [$up] = $this->linksInTheEmail();

        $this->get($up);

        /*
         * No session was created by following it. The portal is exactly as
         * closed to this browser as it was before — which is what stops a
         * forwarded email from becoming somebody else's account.
         */
        $this->getJson('/api/v1/portal/requests')->assertUnauthorized();
    }

    public function test_an_unfinished_ticket_has_no_invitation_at_all(): void
    {
        $this->openTicket();

        $this->assertSame([], $this->transport->sent());
    }

    public function test_the_two_links_differ_only_in_the_answer(): void
    {
        $ticket = $this->openTicket();
        $this->moveTo($ticket, TicketStatus::Resolved);

        [$up, $down] = $this->linksInTheEmail();

        $this->assertStringContainsString('/'.FeedbackInvitation::UP.'?', $up);
        $this->assertStringContainsString('/'.FeedbackInvitation::DOWN.'?', $down);
        $this->assertNotSame($up, $down);
    }
}
