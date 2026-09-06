<?php

declare(strict_types=1);

use App\Modules\Customers\Domain\ContactKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets a customer be known by a chat session.
 *
 * A visitor can open the widget and ask a question having given us no address
 * and no number. They still need a customer record — otherwise the
 * conversation has no owner and cannot become a ticket — and the session id is
 * the only handle we have on them.
 *
 * Both constraints are rebuilt from the same enum, because `customers`
 * carries a preferred channel drawn from the same set and the two have gone
 * out of step before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = implode(',', array_map(
            static fn (ContactKind $case): string => "'".$case->value."'",
            ContactKind::cases(),
        ));

        DB::statement('ALTER TABLE contact_identifiers DROP CONSTRAINT IF EXISTS contact_identifiers_kind_check');
        DB::statement(
            "ALTER TABLE contact_identifiers ADD CONSTRAINT contact_identifiers_kind_check ".
            "CHECK (kind IN ({$allowed}))"
        );

        DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_preferred_channel_check');
        DB::statement(
            "ALTER TABLE customers ADD CONSTRAINT customers_preferred_channel_check ".
            "CHECK (preferred_channel IS NULL OR preferred_channel IN ({$allowed}))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('contact_identifiers')->where('kind', 'chat')->delete();
        DB::table('customers')->where('preferred_channel', 'chat')->update(['preferred_channel' => null]);

        DB::statement('ALTER TABLE contact_identifiers DROP CONSTRAINT IF EXISTS contact_identifiers_kind_check');
        DB::statement(
            "ALTER TABLE contact_identifiers ADD CONSTRAINT contact_identifiers_kind_check ".
            "CHECK (kind IN ('email','phone','whatsapp'))"
        );

        DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_preferred_channel_check');
        DB::statement(
            "ALTER TABLE customers ADD CONSTRAINT customers_preferred_channel_check ".
            "CHECK (preferred_channel IS NULL OR preferred_channel IN ('email','phone','whatsapp'))"
        );
    }
};
