<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Modules\Tickets\Application\Portal\CustomerRequests;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A customer does not read their own words back to themselves twice.
 *
 * The portal renders the description as the opening entry and the thread
 * underneath. A request that arrived by EMAIL has the same text in both:
 * `InboundMailIntake` writes the body into `description` when it opens the
 * ticket and appends it again as the first inbound message — it has to,
 * because that message row carries the `Message-ID` the whole email
 * conversation is correlated by.
 *
 * The customer saw their own sentence, then their own sentence, then the
 * reply. It read like the system had double-posted their complaint.
 */
final class PortalDoesNotEchoTheOpeningTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    public function test_the_opening_is_shown_once(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $body = 'اتخصم مني نفس المبلغ مرتين في نفس اليوم.';

        $ticket = $this->makeTicket(['status' => 'open', 'description' => $body]);

        // What the email intake does: the description, then the same text as
        // the first inbound message.
        app(AppendMessage::class)->handle(
            Actor::system('inbound email'),
            (string) $ticket->getKey(),
            MessageDirection::Inbound,
            $body,
        );

        $payload = app(CustomerRequests::class)
            ->show((string) $ticket->customer_id, (string) $ticket->getKey());

        $this->assertNotNull($payload);
        $this->assertSame($body, $payload['description']);
        $this->assertCount(0, $payload['messages'], 'The opening was echoed under itself.');
    }

    public function test_a_customer_who_really_did_say_it_twice_sees_it_twice(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $body = 'Still nothing has happened.';

        $ticket = $this->makeTicket(['status' => 'open', 'description' => $body]);

        $append = app(AppendMessage::class);
        $actor = Actor::system('inbound email');

        $append->handle($actor, (string) $ticket->getKey(), MessageDirection::Inbound, $body);
        $append->handle($actor, (string) $ticket->getKey(), MessageDirection::Inbound, $body);

        $payload = app(CustomerRequests::class)
            ->show((string) $ticket->customer_id, (string) $ticket->getKey());

        /*
         * Only the first is an echo of the description. A customer writing the
         * same sentence again — because nothing happened the first time — is
         * saying something, and hiding it would hide the complaint.
         */
        $this->assertCount(1, $payload['messages']);
    }

    public function test_a_reply_from_the_desk_is_never_dropped(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $body = 'My invoice is wrong.';

        $ticket = $this->makeTicket(['status' => 'open', 'description' => $body]);

        // Same text, but from the desk. Only an inbound first message can be
        // an echo of what the customer themselves wrote.
        app(AppendMessage::class)->handle(
            Actor::staff('1', 'Hana Support'),
            (string) $ticket->getKey(),
            MessageDirection::Outbound,
            $body,
        );

        $payload = app(CustomerRequests::class)
            ->show((string) $ticket->customer_id, (string) $ticket->getKey());

        $this->assertCount(1, $payload['messages']);
        $this->assertSame('support', $payload['messages'][0]['from']);
    }
}
