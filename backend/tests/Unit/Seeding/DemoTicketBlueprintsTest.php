<?php

declare(strict_types=1);

namespace Tests\Unit\Seeding;

use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Priority;
use Database\Seeders\DemoAgentsSeeder;
use Database\Seeders\DemoCustomersSeeder;
use Database\Seeders\DemoPortalAccountsSeeder;
use Database\Seeders\DemoTicketsSeeder;
use PHPUnit\Framework\TestCase;

/**
 * The catalogue covers what it claims to, without touching a database.
 *
 * The seeded dataset exists to make every screen non-empty, and that is a
 * property of the LIST, not of the seeding. Checking it here means a blueprint
 * edited in a hurry — the last urgent ticket removed, the last `closed` one
 * changed to `open` — fails in a hundred milliseconds instead of surviving
 * until somebody opens a filter and finds nothing behind it.
 */
final class DemoTicketBlueprintsTest extends TestCase
{
    public function test_there_are_at_least_eighteen(): void
    {
        $this->assertGreaterThanOrEqual(18, count(DemoTicketsSeeder::blueprints()));
    }

    public function test_every_status_priority_and_channel_appears(): void
    {
        $blueprints = DemoTicketsSeeder::blueprints();

        foreach (TicketStatus::cases() as $status) {
            $this->assertNotEmpty(
                array_filter($blueprints, static fn (array $b): bool => $b['status'] === $status),
                "No blueprint ends up [{$status->value}], so that filter shows an empty list.",
            );
        }

        foreach (Priority::cases() as $priority) {
            $this->assertNotEmpty(
                array_filter($blueprints, static fn (array $b): bool => $b['priority'] === $priority),
                "No blueprint is [{$priority->value}] priority.",
            );
        }

        foreach (TicketChannel::cases() as $channel) {
            $this->assertNotEmpty(
                array_filter($blueprints, static fn (array $b): bool => $b['channel'] === $channel),
                "No blueprint arrives by [{$channel->value}].",
            );
        }
    }

    public function test_each_agent_holds_more_than_one_and_something_is_unclaimed(): void
    {
        $assignees = array_count_values(array_map(
            static fn (array $b): string => $b['assignee'] ?? '',
            DemoTicketsSeeder::blueprints(),
        ));

        $expected = array_merge(['agent@ragab.test'], array_column(DemoAgentsSeeder::AGENTS, 1));

        foreach ($expected as $email) {
            $this->assertGreaterThanOrEqual(
                2,
                $assignees[$email] ?? 0,
                "{$email} holds fewer than two tickets, so their queue reads as empty.",
            );
        }

        $this->assertGreaterThanOrEqual(2, $assignees[''] ?? 0, 'Nothing is unassigned.');
    }

    public function test_every_thread_is_a_conversation(): void
    {
        foreach (DemoTicketsSeeder::blueprints() as $blueprint) {
            $this->assertGreaterThanOrEqual(
                2,
                count($blueprint['messages']),
                "[{$blueprint['subject']}] has fewer than two messages, so it reads as a shout into a void.",
            );

            $directions = array_column($blueprint['messages'], 0);

            $this->assertContains(MessageDirection::Inbound, $directions);
            $this->assertContains(MessageDirection::Outbound, $directions);
        }
    }

    public function test_at_least_three_threads_carry_an_internal_note(): void
    {
        $withNotes = array_filter(
            DemoTicketsSeeder::blueprints(),
            static fn (array $b): bool => in_array(MessageDirection::Internal, array_column($b['messages'], 0), true),
        );

        $this->assertGreaterThanOrEqual(
            3,
            count($withNotes),
            'Too few internal notes to show that the portal hides them.',
        );
    }

    public function test_anything_that_gets_resolved_says_why(): void
    {
        foreach (DemoTicketsSeeder::blueprints() as $blueprint) {
            if (! in_array($blueprint['status'], [TicketStatus::Resolved, TicketStatus::Closed], true)) {
                continue;
            }

            $this->assertNotSame(
                '',
                $blueprint['resolution'],
                "[{$blueprint['subject']}] is resolved with an empty resolution note.",
            );
        }
    }

    public function test_every_blueprint_points_at_a_customer_that_is_seeded(): void
    {
        $known = array_column(DemoCustomersSeeder::CUSTOMERS, 'email');

        foreach (DemoTicketsSeeder::blueprints() as $blueprint) {
            $this->assertContains(
                $blueprint['customer'],
                $known,
                "[{$blueprint['subject']}] belongs to a customer no seeder creates, so it is silently skipped.",
            );
        }
    }

    public function test_a_portal_ticket_belongs_to_somebody_who_has_a_login(): void
    {
        $registered = array_column(DemoPortalAccountsSeeder::ACCOUNTS, 0);

        foreach (DemoTicketsSeeder::blueprints() as $blueprint) {
            if ($blueprint['channel'] !== TicketChannel::Portal) {
                continue;
            }

            /*
             * Otherwise the ticket claims to have arrived through a portal the
             * customer cannot sign in to, and its creator is recorded as the
             * system — a lie in `creator_type` that no screen would reveal.
             */
            $this->assertContains(
                $blueprint['customer'],
                $registered,
                "[{$blueprint['subject']}] arrived by portal but its customer has no portal account.",
            );
        }
    }

    public function test_no_two_blueprints_share_a_subject_for_one_customer(): void
    {
        $keys = array_map(
            static fn (array $b): string => $b['customer'].'|'.$b['subject'],
            DemoTicketsSeeder::blueprints(),
        );

        // Subject plus customer IS the idempotency key. Two blueprints sharing
        // one would make the second permanently unseedable.
        $this->assertSame(array_unique($keys), $keys, 'Two blueprints share an idempotency key.');
    }
}
