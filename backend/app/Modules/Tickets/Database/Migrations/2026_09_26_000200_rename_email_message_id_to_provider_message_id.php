<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The column already held every channel's ids. Now it says so.
 *
 * `email_message_id` was named when email was the only transport. Story 7.1
 * made `TicketCorrelator` match every channel's threading on it, and this
 * story writes WhatsApp and SMS provider ids into it — so the name has been
 * describing one caller of a general column for two stories.
 *
 * That is not cosmetic. The next person to add a transport reads
 * `email_message_id`, concludes it is not theirs, and adds a second column —
 * at which point threading works for two channels and silently does not for
 * the third, and the correlator has two places to look.
 *
 * The sibling header columns keep their names: `email_in_reply_to` and
 * `email_references` really ARE mail-specific, and renaming them would claim a
 * generality they do not have.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket_messages', 'email_message_id')) {
            return;
        }

        Schema::table('ticket_messages', function (Blueprint $table): void {
            $table->renameColumn('email_message_id', 'provider_message_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ticket_messages', 'provider_message_id')) {
            return;
        }

        Schema::table('ticket_messages', function (Blueprint $table): void {
            $table->renameColumn('provider_message_id', 'email_message_id');
        });
    }
};
