<?php

declare(strict_types=1);

use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets the constraint keep up with the enum it was copied from.
 *
 * `attachments_owner_type_check` was written by hand when there were three
 * owners, and it is pgsql-only. Story 7.1 added `web_form_draft` to the enum
 * and every test passed — the suite runs on SQLite, which has no constraint to
 * violate — while every attachment uploaded through the public web form would
 * have been refused in production. Nothing in the application would have said
 * so: the upload returns 201 and the row is never written.
 *
 * The list is now derived from `AttachmentOwnerType::values()`, so the next
 * owner is one enum case and no second edit. `AttachmentOwnerTypesAreConstrainedTest`
 * fails if the two ever drift again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE attachments DROP CONSTRAINT IF EXISTS attachments_owner_type_check');
        DB::statement(
            'ALTER TABLE attachments ADD CONSTRAINT attachments_owner_type_check CHECK (owner_type IN ('
            .implode(',', array_map(
                static fn (string $value): string => "'".$value."'",
                AttachmentOwnerType::values(),
            ))
            .'))'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        /*
         * Back to the three the constraint was born with. Any row using a
         * later owner type would violate it, so those are removed first —
         * loudly wrong is better than a migration that fails halfway and
         * leaves the table with no constraint at all.
         */
        DB::table('attachments')->whereNotIn('owner_type', ['customer', 'ticket', 'message'])->delete();

        DB::statement('ALTER TABLE attachments DROP CONSTRAINT IF EXISTS attachments_owner_type_check');
        DB::statement(
            "ALTER TABLE attachments ADD CONSTRAINT attachments_owner_type_check CHECK (owner_type IN ('customer','ticket','message'))"
        );
    }
};
