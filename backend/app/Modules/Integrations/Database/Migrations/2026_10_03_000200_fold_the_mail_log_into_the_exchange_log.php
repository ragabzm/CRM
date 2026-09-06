<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "one exchange log" true rather than aspirational.
 *
 * Story 5.1 built a mail log. This story builds an exchange log and says there
 * is ONE of them, not one per integration — so the mail log becomes the first
 * tenant of this table rather than the second table with the same shape. An
 * administrator asking "did anything reach the outside world last night?"
 * should not have to know that mail answers in one place and the ERP in
 * another.
 *
 * The rows COME ACROSS before the old table goes. Replacing a log with a
 * better log is still losing a log if the history does not follow, and those
 * sends are exactly what somebody will want the first week after this ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_exchanges', function (Blueprint $table): void {
            /*
             * What only one integration's rows need: a message id for mail, a
             * ticket, a subject, the provider's own code.
             *
             * A JSON column rather than four nullable ones, because the next
             * integration will want a different four, and a table that grows a
             * column per tenant is a table nobody can read.
             */
            $table->text('context')->nullable()->after('response');
        });

        if (! Schema::hasTable('mail_log')) {
            return;
        }

        foreach (DB::table('mail_log')->orderBy('id')->cursor() as $row) {
            DB::table('integration_exchanges')->insert([
                'id' => $row->id,
                'direction' => $row->direction,
                'integration' => 'email',
                // "endpoint or address" — for mail it is the address.
                'target' => $row->address,
                /*
                 * ONE vocabulary across the log, so "show me everything that
                 * failed last night" is one query rather than a union with a
                 * translation table. Email keeps saying "sent" at its own
                 * boundary; that word is now presentation, not storage.
                 */
                'status' => $row->status === 'sent' ? 'succeeded' : $row->status,
                'attempt' => $row->attempt,
                'response_status' => null,
                'duration_ms' => $row->duration_ms,
                'request' => null,
                'response' => null,
                'context' => json_encode(array_filter([
                    'provider' => $row->provider,
                    'subject' => $row->subject,
                    'message_id' => $row->message_id,
                    'ticket_id' => $row->ticket_id,
                    'provider_code' => $row->provider_code,
                ], static fn ($v): bool => $v !== null), JSON_UNESCAPED_UNICODE),
                'error' => $row->error,
                'occurred_at' => $row->occurred_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('mail_log');
    }

    public function down(): void
    {
        Schema::table('integration_exchanges', function (Blueprint $table): void {
            $table->dropColumn('context');
        });
    }
};
