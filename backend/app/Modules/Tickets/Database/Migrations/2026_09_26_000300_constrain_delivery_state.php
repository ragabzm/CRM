<?php

declare(strict_types=1);

use App\Modules\Tickets\Domain\Enum\DeliveryState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The delivery states, enforced by the database as well as by PHP.
 *
 * `ticket_messages.direction` has had a CHECK since Story 4.4;
 * `delivery_state` never got one, so a typo in a provider adapter could write
 * `deliverd` and the column would take it — and every screen would then show a
 * message in a state nothing knows how to render.
 *
 * Built from `DeliveryState::cases()`, so a state added with a future provider
 * is one enum case and no second edit. `EnumBackedCheckConstraintsTest` covers
 * it from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = implode(',', array_map(
            static fn (DeliveryState $case): string => "'".$case->value."'",
            DeliveryState::cases(),
        ));

        DB::statement('ALTER TABLE ticket_messages DROP CONSTRAINT IF EXISTS ticket_messages_delivery_state_check');
        DB::statement(
            'ALTER TABLE ticket_messages ADD CONSTRAINT ticket_messages_delivery_state_check '
            ."CHECK (delivery_state IS NULL OR delivery_state IN ({$allowed}))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE ticket_messages DROP CONSTRAINT IF EXISTS ticket_messages_delivery_state_check');
    }
};
