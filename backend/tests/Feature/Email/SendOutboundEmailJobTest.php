<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Contracts\MailTransportFailure;
use App\Modules\Email\Domain\MailLogEntry;
use App\Modules\Email\Jobs\SendOutboundEmailJob;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * What happens when the provider does not cooperate.
 *
 * The design rests on one distinction: a refused recipient will be refused
 * identically in five minutes, and a connection reset will not. Retrying the
 * first burns the provider's reputation on a message that can never land and
 * delays the moment anybody finds out; not retrying the second loses a reply
 * over a blip.
 */
final class SendOutboundEmailJobTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->settings()->set('email.enabled', true, null);
        $this->settings()->set('email.acknowledgement.enabled', false, null);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    /** A transport that always fails, the way the caller asks it to. */
    private function transportThatFails(bool $retryable): void
    {
        $this->app->instance(MailTransport::class, new class($retryable) implements MailTransport
        {
            public function __construct(private readonly bool $retryable) {}

            public function send(string $a, string $b, string $c, string $d, array $e, string $f): void
            {
                throw $this->retryable
                    ? MailTransportFailure::temporary('Connection reset by peer', '421')
                    : MailTransportFailure::permanent('Recipient address rejected', '550');
            }

            public function name(): string
            {
                return 'failing';
            }
        });
    }

    private function messageId(): string
    {
        $ticket = $this->makeTicket();
        $agent = $this->makeUser(Roles::AGENT);

        $message = $this->app->make(AppendMessage::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            MessageDirection::Outbound,
            'A reply.',
        );

        return (string) $message->getKey();
    }

    private function runJob(string $messageId): void
    {
        $job = new SendOutboundEmailJob(
            'hana@example.test',
            'Hana',
            'Subject',
            'Body',
            [],
            'en',
            $messageId,
        );

        try {
            $this->app->call([$job, 'handle']);
        } catch (\Throwable) {
            // The queue would catch this and reschedule; the test only cares
            // about what was recorded.
        }
    }

    public function test_a_successful_send_marks_the_message_sent(): void
    {
        $id = $this->messageId();

        $this->runJob($id);

        /*
         * `sent` is what makes the conversation screen stop showing a reply as
         * still on its way. Until this story it never moved off `queued`.
         */
        $this->assertSame('sent', DB::table('ticket_messages')->where('id', $id)->value('delivery_state'));
    }

    public function test_a_successful_send_is_logged(): void
    {
        $this->runJob($this->messageId());

        $entry = MailLogEntry::query()->latest('occurred_at')->firstOrFail();

        $this->assertSame('sent', $entry->status);
        $this->assertSame('hana@example.test', $entry->address);
        $this->assertNotNull($entry->duration_ms);
    }

    public function test_a_permanent_failure_marks_the_message_failed_at_once(): void
    {
        $this->transportThatFails(retryable: false);
        $id = $this->messageId();

        $this->runJob($id);

        // No retries: a rejected recipient will be rejected identically in five
        // minutes, and the agent needs to know now.
        $this->assertSame('failed', DB::table('ticket_messages')->where('id', $id)->value('delivery_state'));
    }

    public function test_a_temporary_failure_does_not_give_up_on_the_first_try(): void
    {
        $this->transportThatFails(retryable: true);
        $id = $this->messageId();

        $this->runJob($id);

        /*
         * Still queued. The job rethrows so the queue reschedules it; marking
         * it failed here would tell the agent their reply was lost over a
         * blip that the next attempt will ride out.
         */
        $this->assertSame('queued', DB::table('ticket_messages')->where('id', $id)->value('delivery_state'));
    }

    public function test_a_failure_records_the_providers_own_words(): void
    {
        $this->transportThatFails(retryable: false);

        $this->runJob($this->messageId());

        $entry = MailLogEntry::query()->latest('occurred_at')->firstOrFail();

        /*
         * "Sending failed" gives an administrator nothing. The provider already
         * diagnosed it, and passing that through is the difference between a
         * five-minute fix and a support ticket.
         */
        $this->assertSame('failed', $entry->status);
        $this->assertStringContainsString('Recipient address rejected', (string) $entry->error);
        $this->assertSame('550', $entry->provider_code);
    }

    public function test_an_attempt_is_logged_before_it_is_made(): void
    {
        $this->app->instance(MailTransport::class, new class implements MailTransport
        {
            public function send(string $a, string $b, string $c, string $d, array $e, string $f): void
            {
                // Killed mid-send, the way a worker being restarted would be.
                throw new \RuntimeException('worker died');
            }

            public function name(): string
            {
                return 'crashing';
            }
        });

        $this->runJob($this->messageId());

        // A log written only on completion is silent about exactly the
        // failures that need explaining.
        $this->assertGreaterThan(0, MailLogEntry::query()->count());
    }

    public function test_a_disabled_channel_fails_permanently_rather_than_retrying(): void
    {
        $this->settings()->set('email.enabled', false, null);
        $id = $this->messageId();

        $this->runJob($id);

        // A switched-off channel is a decision somebody made; retrying it for a
        // day would fill the queue with work that is supposed to not happen.
        $this->assertSame('failed', DB::table('ticket_messages')->where('id', $id)->value('delivery_state'));
    }

    public function test_the_job_retries_with_growing_backoff(): void
    {
        $job = new SendOutboundEmailJob('a@b.test', 'A', 'S', 'B', [], 'en');

        /*
         * Five attempts across roughly ten minutes: long enough to ride out a
         * provider restart, short enough that a dead provider surfaces while
         * the agent is still at their desk rather than the next morning.
         */
        $this->assertSame(5, $job->tries);
        $this->assertSame([30, 60, 120, 300], $job->backoff);
    }
}
