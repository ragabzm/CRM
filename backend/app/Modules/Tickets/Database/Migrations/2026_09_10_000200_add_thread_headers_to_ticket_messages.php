<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The identifiers that keep a customer's mail client showing one conversation.
 *
 * Our thread holds together on `ticket_id` regardless. These columns are for
 * THEIR side: a reply whose `In-Reply-To` does not name a message the client
 * has seen gets filed as a new conversation, and the customer ends up with
 * eight separate emails about one problem.
 *
 * Stored rather than derived, because the value that matters is the one that
 * actually went out. A formula could change; what a customer's mail client
 * already saw cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table): void {
            // RFC 5322 allows 998 octets per line; real Message-IDs are far
            // shorter, and 320 leaves room for a long provider-generated one.
            $table->string('email_message_id', 320)->nullable()->after('delivery_state');
            $table->string('email_in_reply_to', 320)->nullable()->after('email_message_id');

            /*
             * The full References chain, which grows with the thread. Text
             * rather than a bounded string: a long conversation legitimately
             * accumulates dozens of ids, and truncating it silently breaks the
             * threading this column exists to preserve.
             */
            $table->text('email_references')->nullable()->after('email_in_reply_to');

            // Story 5.2 finds the ticket a reply belongs to by this.
            $table->index(['email_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table): void {
            $table->dropIndex(['email_message_id']);
            $table->dropColumn(['email_message_id', 'email_in_reply_to', 'email_references']);
        });
    }
};
