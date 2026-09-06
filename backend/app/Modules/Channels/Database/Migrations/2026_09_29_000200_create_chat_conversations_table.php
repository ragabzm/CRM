<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A live conversation, while it is live.
 *
 * The TRANSCRIPT is not here. Every message is an ordinary `ticket_messages`
 * row on the ticket the first message created, so the history is uniform and
 * the conversation is searchable like any other. A transcript stored as a blob
 * on this table would be a second kind of ticket history that no existing
 * screen, search or export knows about — and Story 7.1's whole claim is that a
 * channel supplies a transport, never a second history.
 *
 * What IS here is the handful of facts that only exist while somebody is
 * waiting: who has taken it, when it was last touched, and when the visitor's
 * token stops working.
 *
 * Deliberately absent, and named here so the absence is a decision rather than
 * an oversight: no agent availability, no presence, no queue position, no
 * priority, no routing rule, no typing state and no read receipt. There is no
 * column for any of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            /*
             * The ticket this conversation became.
             *
             * Null only between "the visitor opened the widget" and "the
             * visitor sent their first message" — a box somebody opened and
             * closed again is not a ticket, and creating one would fill the
             * queue with silence.
             */
            $table->string('ticket_id', 26)->nullable();
            $table->ulid('channel_account_id')->nullable();

            /*
             * The customer record this visitor resolved to. Written when the
             * first message goes through the intake pipeline, which is the
             * moment they stop being anonymous to us.
             */
            $table->string('customer_id', 26)->nullable();

            $table->string('visitor_name', 120)->nullable();
            /** Whatever they typed into "how can we reach you" — may be nothing. */
            $table->string('visitor_identifier', 320)->nullable();

            /*
             * The origin the widget was embedded on, recorded at issue.
             *
             * Not a security control on its own — the allow-list is checked
             * before a token is issued — but the answer to "where did this
             * conversation come from?", which nothing else can give once the
             * ticket exists.
             */
            $table->string('origin', 255)->nullable();

            /*
             * Who has it. Null means waiting, and the conditional update that
             * claims it (`WHERE taken_by IS NULL`) is what makes two agents
             * clicking at once resolve in the database rather than in the UI.
             */
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at')->nullable();

            $table->timestamp('ended_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();

            /*
             * The sweep reads this. Touched by every message either way, so
             * "inactive" means nobody has said anything — not that the visitor
             * stopped typing.
             */
            $table->timestamp('last_activity_at');

            /*
             * The visitor's token, HASHED.
             *
             * Stored the way a password is, for the same reason: this row is
             * read by admin screens, exports and anybody with database access,
             * and a plaintext credential sitting in a table is a credential
             * that leaks through a channel nobody thought of. Verification
             * looks the row up by hash, so the plaintext exists only in the
             * response that issued it and in the visitor's own browser.
             */
            $table->string('token_hash', 64)->unique();

            /*
             * When it stops working. Short-lived on purpose: a token that
             * never expires is a permanent write credential left in a browser
             * tab.
             */
            $table->timestamp('token_expires_at');

            $table->timestamps();

            // The waiting list: untaken, unfinished, oldest first.
            $table->index(['taken_by', 'ended_at', 'created_at']);
            // The abandonment sweep.
            $table->index(['ended_at', 'abandoned_at', 'last_activity_at']);
            $table->index(['ticket_id']);
        });

        /*
         * A conversation is waiting, taken, ended or abandoned — never both of
         * the last two.
         *
         * An ended conversation is one somebody closed; an abandoned one is
         * one the sweep gave up on. A row claiming both would make "how many
         * did we actually answer?" a question with two answers, and the
         * reports in Epic 11 read exactly that.
         */
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE chat_conversations ADD CONSTRAINT chat_conversations_finish_check CHECK (
                    ended_at IS NULL OR abandoned_at IS NULL
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
