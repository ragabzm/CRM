<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\User;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Portal\Domain\PortalAccount;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Commands\RateTicket;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A customer says whether it went well, in one tap.
 *
 * The rules worth the most attention are the three about what CANNOT happen:
 * nobody else can rate your ticket, staff cannot edit what you said, and there
 * is no numeric value anywhere for anybody to average.
 */
final class CustomerFeedbackTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private PortalAccount $account;

    private string $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->make(SettingsRegistry::class)->set('email.enabled', false, null);

        $this->customerId = $this->makeCustomer();

        $this->account = new PortalAccount;
        $this->account->forceFill([
            'name' => 'Hana Yousef',
            'email' => 'hana@example.test',
            'password' => 'a-long-enough-passphrase',
            'preferred_locale' => 'en',
            'customer_id' => $this->customerId,
        ])->save();
    }

    private function finishedTicket(string $status = 'resolved'): Ticket
    {
        return $this->makeTicket([
            'customer_id' => $this->customerId,
            'status' => $status,
            'resolved_at' => now(),
        ]);
    }

    private function rate(Ticket $ticket, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->account, 'portal')
            ->withIdempotencyKey()
            ->postJson('/api/v1/portal/requests/'.$ticket->getKey().'/rating', $body);
    }

    public function test_a_customer_rates_a_finished_request_in_one_tap(): void
    {
        $ticket = $this->finishedTicket();

        $response = $this->rate($ticket, ['positive' => true]);

        $response->assertOk();

        // No comment, and it is complete. A rating alone is an answer.
        $this->assertTrue($ticket->refresh()->satisfaction);
        $this->assertNull($ticket->refresh()->satisfaction_comment);
        $this->assertNotNull($ticket->refresh()->satisfaction_at);
    }

    public function test_a_comment_is_optional_and_kept_when_given(): void
    {
        $ticket = $this->finishedTicket();

        $this->rate($ticket, ['positive' => false, 'comment' => 'It took four days.'])->assertOk();

        $this->assertFalse($ticket->refresh()->satisfaction);
        $this->assertSame('It took four days.', $ticket->refresh()->satisfaction_comment);
    }

    public function test_there_are_exactly_two_values_and_nothing_numeric(): void
    {
        $ticket = $this->finishedTicket();

        /*
         * No scale, and nothing that could become one. A client cannot send a
         * star count, a 0–10 or a midpoint, because the column is a boolean
         * and the validation refuses anything else.
         */
        foreach ([3, 5, 'excellent', 0.5, [], 'null'] as $notABoolean) {
            $this->rate($ticket, ['positive' => $notABoolean])->assertStatus(422);
        }

        $this->assertNull($ticket->refresh()->satisfaction);

        // And the two that are allowed really are just two.
        $this->rate($ticket, ['positive' => true])->assertOk();
        $this->assertSame('boolean', gettype($ticket->refresh()->satisfaction));
    }

    public function test_an_unrated_ticket_is_neither_positive_nor_negative(): void
    {
        $ticket = $this->finishedTicket();

        /*
         * Null is the third state. It means nobody answered — not "neutral",
         * not zero, and not a middling score that would drag a positive rate
         * towards the middle on the strength of silence.
         */
        $this->assertNull($ticket->satisfaction);

        $shown = $this->actingAs($this->account, 'portal')
            ->getJson('/api/v1/portal/requests/'.$ticket->getKey());

        $shown->assertOk();
        $this->assertNull($shown->json('satisfaction'));
        $this->assertTrue($shown->json('can_rate'));
    }

    public function test_a_request_still_open_cannot_be_rated_yet(): void
    {
        $ticket = $this->makeTicket(['customer_id' => $this->customerId, 'status' => 'open']);

        $response = $this->rate($ticket, ['positive' => true]);

        // Asking how it went while it is still going asks somebody to judge
        // unfinished work.
        $response->assertStatus(422);
        $this->assertSame('tickets.not_finished', $response->json('code'));
    }

    public function test_a_closed_request_can_be_rated_too(): void
    {
        $ticket = $this->finishedTicket('closed');

        $this->rate($ticket, ['positive' => true])->assertOk();
        $this->assertTrue($ticket->refresh()->satisfaction);
    }

    public function test_a_second_answer_inside_the_window_replaces_the_first(): void
    {
        $ticket = $this->finishedTicket();

        $this->rate($ticket, ['positive' => true, 'comment' => 'Fine.'])->assertOk();
        $this->rate($ticket, ['positive' => false, 'comment' => 'Actually it was not.'])->assertOk();

        // Rated once. The second answer is the answer, not a second row.
        $this->assertFalse($ticket->refresh()->satisfaction);
        $this->assertSame('Actually it was not.', $ticket->refresh()->satisfaction_comment);
    }

    public function test_clearing_the_comment_withdraws_it_rather_than_keeping_the_old_words(): void
    {
        $ticket = $this->finishedTicket();

        $this->rate($ticket, ['positive' => false, 'comment' => 'It took four days.'])->assertOk();
        $this->rate($ticket, ['positive' => true, 'comment' => ''])->assertOk();

        /*
         * Somebody who changed their mind and deleted what they wrote has
         * withdrawn the words. Leaving them attached to the opposite verdict
         * would misrepresent them.
         */
        $this->assertNull($ticket->refresh()->satisfaction_comment);
    }

    public function test_past_the_window_it_is_locked_and_says_so(): void
    {
        $this->app->make(SettingsRegistry::class)
            ->set(RateTicket::WINDOW_SETTING, 24, null);

        $ticket = $this->finishedTicket();

        $this->rate($ticket, ['positive' => true])->assertOk();

        // Two days later.
        $ticket->forceFill(['satisfaction_at' => now()->subDays(2)])->save();

        $response = $this->rate($ticket, ['positive' => false]);

        /*
         * Refused with the reason, never silently ignored. A tap that appears
         * to do nothing makes somebody tap again and then conclude the product
         * is broken — and they would be right.
         */
        $response->assertStatus(409);
        $this->assertSame('tickets.rating_locked', $response->json('code'));
        $this->assertNotNull($response->json('locked_at'));

        // And the original answer stands.
        $this->assertTrue($ticket->refresh()->satisfaction);
    }

    public function test_the_portal_says_when_rating_has_closed_rather_than_offering_it(): void
    {
        $this->app->make(SettingsRegistry::class)->set(RateTicket::WINDOW_SETTING, 24, null);

        $ticket = $this->finishedTicket();
        $this->rate($ticket, ['positive' => true])->assertOk();
        $ticket->forceFill(['satisfaction_at' => now()->subDays(2)])->save();

        $shown = $this->actingAs($this->account, 'portal')
            ->getJson('/api/v1/portal/requests/'.$ticket->getKey());

        // Computed on the SERVER: a screen that worked the window out itself
        // would offer a control the API then refuses.
        $this->assertFalse($shown->json('can_rate'));
        $this->assertTrue($shown->json('satisfaction'));
    }

    public function test_a_window_of_zero_locks_the_answer_immediately(): void
    {
        $this->app->make(SettingsRegistry::class)->set(RateTicket::WINDOW_SETTING, 0, null);

        $ticket = $this->finishedTicket();

        $this->rate($ticket, ['positive' => true])->assertOk();
        $this->rate($ticket, ['positive' => false])->assertStatus(409);

        $this->assertTrue($ticket->refresh()->satisfaction);
    }

    public function test_nobody_can_rate_somebody_elses_request(): void
    {
        $stranger = new Customer([
            'reference' => Customer::mintReference(),
            'full_name' => 'Somebody Else',
            'state' => 'active',
        ]);
        $stranger->setAttribute('id', (string) \Illuminate\Support\Str::ulid());
        $stranger->save();

        $theirs = $this->makeTicket([
            'customer_id' => $stranger->getKey(),
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->rate($theirs, ['positive' => true]);

        /*
         * 404, never 403 — by the portal's own confinement, and by a guessed
         * id like any other. A 403 would confirm the ticket exists, which is
         * itself information about somebody else's business.
         */
        $response->assertNotFound();
        $this->assertSame('portal.request_not_found', $response->json('code'));
        $this->assertNull($theirs->refresh()->satisfaction);
    }

    public function test_the_rating_is_recorded_in_history_with_the_customer_as_the_actor(): void
    {
        $ticket = $this->finishedTicket();

        $this->rate($ticket, ['positive' => true, 'comment' => 'Quick and clear.'])->assertOk();

        $event = DB::table('ticket_events')
            ->where('ticket_id', $ticket->getKey())
            ->where('event_type', 'ticket.rated')
            ->first();

        $this->assertNotNull($event);
        // The one thing on a ticket's history the CUSTOMER wrote themselves.
        $this->assertSame('portal', $event->actor_type);
        $this->assertNotNull($event->created_at);
        $this->assertStringContainsString('Quick and clear.', (string) $event->payload);
    }

    public function test_a_change_is_recorded_as_its_own_event(): void
    {
        $ticket = $this->finishedTicket();

        $this->rate($ticket, ['positive' => true])->assertOk();
        $this->rate($ticket, ['positive' => false])->assertOk();

        // Two events: what they first said, and that they changed it.
        $this->assertSame(
            2,
            DB::table('ticket_events')->where('event_type', 'ticket.rated')->count(),
        );
    }

    public function test_rating_carries_no_version_and_changes_none(): void
    {
        $ticket = $this->finishedTicket();
        $before = $ticket->version;

        $this->rate($ticket, ['positive' => true])->assertOk();

        /*
         * A customer has no version and never sees one. Bumping it would
         * invalidate the screen of the agent reading the ticket, for a change
         * that altered nothing they were looking at.
         */
        $this->assertSame($before, $ticket->refresh()->version);
    }

    public function test_staff_see_the_rating_and_have_no_way_to_change_it(): void
    {
        $ticket = $this->finishedTicket();
        $this->rate($ticket, ['positive' => false, 'comment' => 'Too slow.'])->assertOk();

        \Illuminate\Support\Facades\Auth::guard('portal')->logout();
        $this->flushSession();

        $agent = User::factory()->create();
        $agent->syncRoles([Roles::ADMINISTRATOR]);

        /*
         * `'web'` named EXPLICITLY. `actingAs($user)` uses the DEFAULT guard,
         * and `actingAs($account, 'portal')` above made that `portal` for the
         * rest of the test — so the agent was being signed into the portal
         * guard and the staff routes answered 401. It reads exactly like the
         * agent being refused, which is the wrong lesson entirely.
         */
        $this->actingAs($agent->refresh(), 'web');

        $seen = $this->getJson('/api/v1/tickets/'.$ticket->getKey());
        $seen->assertOk();
        $this->assertFalse($seen->json('satisfaction'));
        $this->assertSame('Too slow.', $seen->json('satisfaction_comment'));

        /*
         * And no way to write it. Not as a PATCH field, not as an admin
         * action. A satisfaction figure staff can edit is a figure nobody has
         * any reason to believe — so the attempt is refused and the value
         * stands.
         */
        $this->withIdempotencyKey()
            ->patchJson('/api/v1/tickets/'.$ticket->getKey(), [
                'version' => $ticket->refresh()->version,
                'satisfaction' => true,
            ])
            ->assertStatus(422);

        $this->assertFalse($ticket->refresh()->satisfaction);
    }
}
