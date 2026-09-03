<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The language this customer wants to be answered in.
 *
 * Belongs to the CUSTOMER, not to the email channel that first needed it: a
 * person who writes in Arabic wants Arabic in an SMS and on the portal too,
 * and putting the preference in the Email module would mean every later
 * channel either duplicated it or ignored it.
 *
 * Nullable, and read as English when unset. That is a fallback, not a default
 * anybody chose — the difference matters because "we do not know yet" should
 * become knowable later, and a NOT NULL column with a default would silently
 * record a preference the customer never expressed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('preferred_locale', 8)->nullable()->after('preferred_channel');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('preferred_locale');
        });
    }
};
