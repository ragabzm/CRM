<?php

declare(strict_types=1);

use App\Modules\Customers\Domain\ContactKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets a customer record hold a WhatsApp number.
 *
 * Two constraints, both hand-typed when there were two kinds, both pgsql-only
 * — so the suite on SQLite would have passed every test while production
 * refused every WhatsApp identifier and every attempt to set WhatsApp as
 * somebody's preferred channel. That is the fourth time this schema has had a
 * CHECK fall behind its enum; `EnumBackedCheckConstraintsTest` now covers both
 * of these too.
 *
 * Rebuilt from `ContactKind::values()`, so the next kind is one enum case.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = implode(',', array_map(
            static fn (string $value): string => "'".$value."'",
            ContactKind::values(),
        ));

        DB::statement('ALTER TABLE contact_identifiers DROP CONSTRAINT IF EXISTS contact_identifiers_kind_check');
        DB::statement("ALTER TABLE contact_identifiers ADD CONSTRAINT contact_identifiers_kind_check CHECK (kind IN ({$allowed}))");

        /*
         * The same list on `preferred_channel`. A customer whose WhatsApp
         * number is on record should be able to say that is how they want to
         * be reached — and until now the column would have refused it.
         */
        DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_preferred_channel_check');
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_preferred_channel_check CHECK (preferred_channel IS NULL OR preferred_channel IN ({$allowed}))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Rows using a kind the old constraint forbids would block the
        // constraint from being recreated. Removed loudly rather than leaving
        // the table with no constraint at all.
        DB::table('contact_identifiers')->whereNotIn('kind', ['email', 'phone'])->delete();
        DB::table('customers')->whereNotIn('preferred_channel', ['email', 'phone'])->whereNotNull('preferred_channel')->update(['preferred_channel' => null]);

        DB::statement('ALTER TABLE contact_identifiers DROP CONSTRAINT IF EXISTS contact_identifiers_kind_check');
        DB::statement("ALTER TABLE contact_identifiers ADD CONSTRAINT contact_identifiers_kind_check CHECK (kind IN ('email','phone'))");

        DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_preferred_channel_check');
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_preferred_channel_check CHECK (preferred_channel IS NULL OR preferred_channel IN ('email','phone'))");
    }
};
