<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\DeliveryState;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * Retrying a send that failed.
 *
 * Retry, not "send again": the message already exists and already records who
 * wrote it and when. Creating a second one would put the agent's words in the
 * thread twice for a failure that was never theirs.
 */
final class TicketMessagesRetryTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private Ticket $ticket;

    private TicketMessage $message;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->ticket = $this->makeTicket();

        $agent = $this->makeUser(Roles::AGENT);

        $this->message = $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $this->ticket->getKey(),
            MessageDirection::Outbound,
            'We have credited the duplicate charge.',
        );
    }

    private function retry(?string $messageId = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson(sprintf(
                '/api/v1/tickets/%s/messages/%s/retry',
                $this->ticket->getKey(),
                $messageId ?? $this->message->getKey(),
            ));
    }

    private function markFailed(): void
    {
        $this->message->forceFill(['delivery_state' => DeliveryState::Failed->value])->save();
    }

    public function test_a_new_reply_starts_queued(): void
    {
        // Not "sent". The database accepting it says nothing about the
        // customer's inbox.
        $this->assertSame(DeliveryState::Queued, $this->message->refresh()->delivery_state);
    }

    public function test_retrying_a_failed_send_queues_it_again(): void
    {
        $this->markFailed();

        $this->retry()->assertOk()->assertJsonPath('delivery_state', 'queued');
    }

    public function test_retrying_creates_no_second_message(): void
    {
        $this->markFailed();

        $this->retry()->assertOk();

        // The agent's words appear once. A duplicate would be the customer's
        // problem, not the mail pipeline's.
        $this->assertSame(1, TicketMessage::query()->where('ticket_id', $this->ticket->getKey())->count());
    }

    public function test_two_rapid_retries_do_not_duplicate_anything(): void
    {
        $this->markFailed();

        $this->retry()->assertOk();
        // The second click finds it already queued and is refused as
        // not-retryable, which is the correct answer — nothing is lost and
        // nothing is sent twice.
        $this->retry()->assertStatus(422);

        $this->assertSame(1, TicketMessage::query()->count());
    }

    public function test_a_message_that_is_still_on_its_way_cannot_be_retried(): void
    {
        // Re-queueing something already in flight would send it twice.
        $this->retry()->assertStatus(422)
            ->assertJsonPath('code', 'tickets.message_not_retryable');
    }

    public function test_an_inbound_message_cannot_be_retried(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $inbound = $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $this->ticket->getKey(),
            MessageDirection::Inbound,
            'Any news?',
        );

        // It has nowhere to go. It arrived.
        $this->retry((string) $inbound->getKey())->assertStatus(422);
    }

    public function test_a_message_on_another_ticket_is_not_found(): void
    {
        $other = $this->makeTicket();

        $response = $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson(sprintf(
                '/api/v1/tickets/%s/messages/%s/retry',
                $other->getKey(),
                $this->message->getKey(),
            ));

        // Scoped to the ticket in the URL, so a valid message id cannot be
        // driven through a ticket it does not belong to.
        $response->assertStatus(404);
    }

    public function test_an_agent_who_cannot_see_the_ticket_cannot_retry(): void
    {
        $this->markFailed();

        $owner = $this->makeUser(Roles::AGENT);
        $this->ticket->forceFill(['assignee_id' => $owner->getKey()])->save();

        $this->actingAs($this->makeUser(Roles::AGENT))
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson(sprintf(
                '/api/v1/tickets/%s/messages/%s/retry',
                $this->ticket->getKey(),
                $this->message->getKey(),
            ))
            ->assertStatus(404);

        $this->assertSame(DeliveryState::Failed, $this->message->refresh()->delivery_state);
    }
}
