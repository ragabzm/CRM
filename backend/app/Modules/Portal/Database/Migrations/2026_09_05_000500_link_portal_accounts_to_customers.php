<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a portal login to the customer record it speaks for.
 *
 * Without it a portal user opening a ticket would have to name the customer in
 * the request body — and could then name any customer at all, and read the
 * ticket back. The account has to know who it is; the request must not be asked.
 *
 * Nullable, because accounts already exist and there is not yet a flow that
 * creates the link. An unlinked account is refused at the point of use rather
 * than defaulted to anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_accounts', function (Blueprint $table): void {
            $table->foreignUlid('customer_id')->nullable()->after('email')
                ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('portal_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
