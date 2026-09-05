<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RunsAgainstRealPostgres;
use Tests\TestCase;

/**
 * Every CHECK constraint that copies a PHP enum still lists all of it.
 *
 * These constraints exist only on Postgres. The suite runs on SQLite, so a
 * constraint that has fallen behind its enum is INVISIBLE to every other test:
 * the code writes the new value happily, the tests pass, and production refuses
 * the insert. It has now happened three times in this schema —
 * `ticket_messages.direction` when internal notes arrived, `tickets.channel`
 * when the web form arrived, `attachments.owner_type` when the web-form draft
 * owner arrived, and `contact_identifiers.kind` plus
 * `customers.preferred_channel` when WhatsApp arrived. The third was written
 * and shipped in the same session that fixed the second; the fourth and fifth
 * were caught by this test before they shipped at all, which is what it is
 * for.
 *
 * So the rule is checked directly, against the running database, once per
 * constrained column. Skipped rather than passed when Postgres is unreachable:
 * a guard that silently succeeds where it cannot look is the thing it is
 * guarding against.
 */
final class EnumBackedCheckConstraintsTest extends TestCase
{
    use RunsAgainstRealPostgres;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useRealPostgres('the CHECK constraints that only Postgres enforces');
    }

    protected function tearDown(): void
    {
        $this->releaseRealPostgres();
        parent::tearDown();
    }

    /**
     * Constraint name → the values the application may write.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function constraints(): array
    {
        return [
            'attachments.owner_type' => ['attachments_owner_type_check', AttachmentOwnerType::values()],
            'tickets.channel' => ['tickets_channel_check', array_column(TicketChannel::cases(), 'value')],
            'tickets.status' => ['tickets_status_check', array_column(TicketStatus::cases(), 'value')],
            'contact_identifiers.kind' => [
                'contact_identifiers_kind_check',
                \App\Modules\Customers\Domain\ContactKind::values(),
            ],
            'customers.preferred_channel' => [
                'customers_preferred_channel_check',
                \App\Modules\Customers\Domain\ContactKind::values(),
            ],
            'ticket_messages.delivery_state' => [
                'ticket_messages_delivery_state_check',
                array_column(\App\Modules\Tickets\Domain\Enum\DeliveryState::cases(), 'value'),
            ],
            'ticket_messages.direction' => [
                'ticket_messages_direction_check',
                array_column(MessageDirection::cases(), 'value'),
            ],
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('constraints')]
    public function test_the_constraint_lists_every_value_the_enum_allows(string $constraint, array $allowed): void
    {
        $definition = DB::selectOne(
            'select pg_get_constraintdef(oid) as def from pg_constraint where conname = ?',
            [$constraint],
        );

        $this->assertNotNull($definition, "No constraint named [{$constraint}] exists.");

        foreach ($allowed as $value) {
            $this->assertStringContainsString(
                "'".$value."'",
                (string) $definition->def,
                "The constraint [{$constraint}] does not allow [{$value}], which the application writes. "
                .'Every test on SQLite will pass and every write in production will be refused.',
            );
        }
    }
}
