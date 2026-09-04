<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The customer timeline shows interactions WITH the customer.
 *
 * It is on the staff-facing profile, so an agent seeing an internal note
 * would not be a leak. What it was is a LIE: the query sorted messages with
 * `case when inbound then inbound else outbound`, so a note — which is
 * neither — was rendered as "outbound message". An agent scrolling a
 * customer's history saw a colleague's private note labelled as something the
 * desk had sent to that person.
 *
 * On the seeded data that read as the business telling a customer, in writing,
 * that its own payment gateway was double-charging her.
 */
final class CustomerTimelineHidesNotesTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    public function test_an_internal_note_is_not_on_the_customers_timeline(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $agent = User::factory()->create();
        $agent->assignRole(Roles::SUPERVISOR);

        $ticket = $this->makeTicket(['status' => 'open']);
        $actor = Actor::staff((string) $agent->getKey(), $agent->name);

        $append = app(AppendMessage::class);

        $append->handle($actor, (string) $ticket->getKey(), MessageDirection::Inbound, 'My invoice is wrong.');
        $append->handle($actor, (string) $ticket->getKey(), MessageDirection::Internal, 'Third time this month.');
        $append->handle($actor, (string) $ticket->getKey(), MessageDirection::Outbound, 'We are looking into it.');

        $body = $this->actingAs($agent)
            ->getJson("/api/v1/customers/{$ticket->customer_id}/timeline")
            ->assertOk()
            ->json();

        $previews = array_column($body['data'] ?? [], 'preview');

        $this->assertContains('My invoice is wrong.', $previews);
        $this->assertContains('We are looking into it.', $previews);

        $this->assertNotContains(
            'Third time this month.',
            $previews,
            'A colleague\'s note is on the customer timeline, where every non-inbound row reads as "sent to the customer".',
        );
    }

    public function test_nothing_on_the_timeline_is_mislabelled_as_outbound(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $agent = User::factory()->create();
        $agent->assignRole(Roles::SUPERVISOR);

        $ticket = $this->makeTicket(['status' => 'open']);
        $actor = Actor::staff((string) $agent->getKey(), $agent->name);

        app(AppendMessage::class)
            ->handle($actor, (string) $ticket->getKey(), MessageDirection::Internal, 'Note only.');

        $kinds = array_column(
            $this->actingAs($agent)
                ->getJson("/api/v1/customers/{$ticket->customer_id}/timeline")
                ->assertOk()
                ->json('data'),
            'kind',
        );

        // The ticket's own creation entry survives; the note contributes
        // nothing at all rather than an entry of the wrong kind.
        $this->assertNotContains('message_outbound', $kinds);
    }
}
