<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Commands\TicketAttributeChanges;
use App\Modules\Tickets\Domain\Commands\UpdateTicketAttributes;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Notifications\CustomerReplied;
use App\Modules\Tickets\Notifications\TicketAssigned;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Three triggers, and no others.
 *
 * The rules that make notifications tolerable rather than noise: nobody hears
 * about their own action, nothing goes to somebody who has left, and every
 * message is written in the language of the person reading it — not the person
 * who caused it.
 */
final class TicketNotificationsTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->app->make(SettingsRegistry::class)->set('email.enabled', false, null);
    }

    private function assign(Ticket $ticket, User $to, User $by): void
    {
        $this->app->make(UpdateTicketAttributes::class)->handle(
            Actor::staff((string) $by->getKey(), $by->name),
            (string) $ticket->getKey(),
            $ticket->refresh()->version,
            TicketAttributeChanges::of(['assignee_id' => $to->getKey()]),
        );
    }

    public function test_assigning_a_ticket_tells_the_new_owner(): void
    {
        Notification::fake();

        $agent = $this->makeUser(Roles::AGENT);
        $supervisor = $this->makeUser(Roles::SUPERVISOR);
        $ticket = $this->makeTicket();

        $this->assign($ticket, $agent, $supervisor);

        Notification::assertSentTo($agent, TicketAssigned::class);
    }

    public function test_nobody_is_told_about_their_own_action(): void
    {
        Notification::fake();

        $agent = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket();

        // Picking up a ticket yourself.
        $this->assign($ticket, $agent, $agent);

        /*
         * A system that emails you about what you just did trains people to
         * ignore its notifications — and then the one that mattered is ignored
         * too.
         */
        Notification::assertNothingSent();
    }

    public function test_reassigning_to_the_same_person_tells_nobody(): void
    {
        $agent = $this->makeUser(Roles::AGENT);
        $supervisor = $this->makeUser(Roles::SUPERVISOR);
        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey()]);

        Notification::fake();

        try {
            $this->assign($ticket, $agent, $supervisor);
        } catch (\Throwable) {
            // The command may refuse a no-op change; either way nothing is sent.
        }

        // Nothing changed, so there is nothing to tell them.
        Notification::assertNothingSent();
    }

    public function test_a_deactivated_account_is_not_notified(): void
    {
        Notification::fake();

        $agent = $this->makeUser(Roles::AGENT);
        $agent->forceFill(['is_active' => false])->save();

        $ticket = $this->makeTicket();
        $this->assign($ticket, $agent, $this->makeUser(Roles::SUPERVISOR));

        // Mail to somebody who has left is useless, and a ticket subject
        // arriving at an unwatched address is a small leak.
        Notification::assertNothingSent();
    }

    public function test_a_customer_reply_tells_the_assignee(): void
    {
        Notification::fake();

        $agent = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey()]);

        $this->app->make(AppendMessage::class)->handle(
            Actor::system('inbound_email'),
            (string) $ticket->getKey(),
            MessageDirection::Inbound,
            'Any news?',
        );

        /*
         * The trigger that most often decides whether a ticket moves today: the
         * ticket looks unchanged from the outside, so without this the agent
         * has no reason to open it again.
         */
        Notification::assertSentTo($agent, CustomerReplied::class);
    }

    public function test_an_agent_reply_notifies_nobody(): void
    {
        Notification::fake();

        $agent = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey()]);

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            MessageDirection::Outbound,
            'Looking into it.',
        );

        // Three triggers, and this is not one of them.
        Notification::assertNothingSent();
    }

    public function test_an_internal_note_notifies_nobody(): void
    {
        Notification::fake();

        $agent = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey()]);

        $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            MessageDirection::Internal,
            'Second time this month.',
        );

        Notification::assertNothingSent();
    }

    public function test_an_unassigned_ticket_notifies_nobody_on_reply(): void
    {
        Notification::fake();

        $ticket = $this->makeTicket(['assignee_id' => null]);

        $this->app->make(AppendMessage::class)->handle(
            Actor::system('inbound_email'),
            (string) $ticket->getKey(),
            MessageDirection::Inbound,
            'Any news?',
        );

        // Nobody to tell. Falling back to "everybody" would turn one reply into
        // an email to the whole desk.
        Notification::assertNothingSent();
    }

    public function test_it_reaches_both_channels(): void
    {
        Notification::fake();

        $agent = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket();

        $this->assign($ticket, $agent, $this->makeUser(Roles::SUPERVISOR));

        Notification::assertSentTo($agent, TicketAssigned::class, function ($notification, array $channels): bool {
            /*
             * `database` is what the bell reads; `mail` is what reaches
             * somebody who is not looking at the application.
             */
            return in_array('database', $channels, true) && in_array('mail', $channels, true);
        });
    }

    public function test_dispatch_is_queued(): void
    {
        $agent = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket();

        $notification = new TicketAssigned((string) $ticket->getKey(), 'TKT-1', 'Subject', 'Dana');

        /*
         * A trigger is somebody pressing Save. Waiting on an SMTP handshake to
         * tell them it worked would make a slow mail provider look like a slow
         * application.
         */
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }
}
