<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Folds `mail_inbound` into the shared table and removes it.
 *
 * Not "creates the new one and leaves the old alive". Two tables holding the
 * same messages is the state where one of them silently stops being written to
 * and nobody notices for a month, and where "have we seen this message?" has
 * two answers.
 *
 * The copy runs inside one transaction: a failure halfway leaves `mail_inbound`
 * exactly as it was and the deploy fails cleanly rather than half-applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_inbound')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('mail_inbound')->orderBy('id')->chunk(500, function ($rows): void {
                $batch = [];

                foreach ($rows as $row) {
                    $batch[] = [
                        'id' => $row->id,
                        'channel' => 'email',
                        'provider_message_id' => $row->external_id,
                        'channel_account_id' => null,
                        'ticket_id' => $row->ticket_id,
                        'message_id' => $row->message_id,
                        'customer_id' => $row->customer_id,
                        'direction' => 'inbound',
                        'sender_identifier' => (string) ($row->from_address ?? ''),
                        'recipient_identifier' => null,
                        'subject' => $row->subject,
                        'body' => null,
                        'headers' => null,
                        'raw_payload' => null,
                        // `received` | `correlated` | `quarantined` carried
                        // over verbatim: the old column held the same words.
                        'delivery_state' => $row->status,
                        'correlation_reason' => self::winningRule($row->correlation_trace),
                        'department_rule' => null,
                        'correlation_trace' => $row->correlation_trace,
                        'received_at' => $row->received_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                if ($batch !== []) {
                    DB::table('inbound_messages')->insert($batch);
                }
            });
        });

        Schema::drop('mail_inbound');
    }

    /**
     * Lifts the winning rule out of the trace it was already recorded in.
     *
     * The old table stored only the JSON blob; the new one promotes the answer
     * to a column so "why did this land here?" is a filter rather than a scan.
     */
    private static function winningRule(mixed $trace): ?string
    {
        if (! is_string($trace)) {
            return null;
        }

        $decoded = json_decode($trace, true);

        return is_array($decoded) && is_string($decoded['winning_rule'] ?? null)
            ? $decoded['winning_rule']
            : null;
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_inbound')) {
            return;
        }

        Schema::create('mail_inbound', function ($table): void {
            $table->ulid('id')->primary();
            $table->string('external_id', 512)->unique();
            $table->string('provider', 32);
            $table->string('status', 16);
            $table->string('from_address', 320)->nullable();
            $table->string('subject', 512)->nullable();
            $table->string('ticket_id', 26)->nullable();
            $table->string('message_id', 26)->nullable();
            $table->string('customer_id', 26)->nullable();
            $table->json('correlation_trace')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['status', 'received_at']);
            $table->index(['ticket_id']);
        });

        /*
         * Copies the email rows back BEFORE the table above it is dropped by
         * the next rollback step. If `inbound_messages` is already gone the
         * copy is a no-op and the operator restores from backup — there is
         * nothing left here to reconstruct it from, and pretending otherwise
         * would be worse than saying so.
         */
        if (! Schema::hasTable('inbound_messages')) {
            return;
        }

        DB::table('inbound_messages')->where('channel', 'email')->orderBy('id')->chunk(500, function ($rows): void {
            $batch = [];

            foreach ($rows as $row) {
                $batch[] = [
                    'id' => $row->id,
                    'external_id' => $row->provider_message_id,
                    'provider' => 'email',
                    'status' => $row->delivery_state,
                    'from_address' => $row->sender_identifier === '' ? null : $row->sender_identifier,
                    'subject' => $row->subject,
                    'ticket_id' => $row->ticket_id,
                    'message_id' => $row->message_id,
                    'customer_id' => $row->customer_id,
                    'correlation_trace' => $row->correlation_trace,
                    'received_at' => $row->received_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            if ($batch !== []) {
                DB::table('mail_inbound')->insert($batch);
            }
        });
    }
};
