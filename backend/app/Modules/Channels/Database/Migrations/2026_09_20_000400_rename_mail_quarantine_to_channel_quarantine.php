<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The quarantine becomes everybody's.
 *
 * Renamed rather than replaced: the rows in it are raw payloads an
 * administrator has not dealt with yet, and a new table beside the old one
 * would leave half the outstanding work in a list nobody opens any more.
 *
 * Existing rows are email by definition — it was the only channel — so the new
 * column defaults to `email` and every historical row is correct without a
 * backfill pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_quarantine')) {
            return;
        }

        if (Schema::hasTable('mail_quarantine')) {
            Schema::rename('mail_quarantine', 'channel_quarantine');
        } else {
            $this->createFresh();
        }

        if (! Schema::hasColumn('channel_quarantine', 'channel')) {
            Schema::table('channel_quarantine', function (Blueprint $table): void {
                $table->string('channel', 32)->default('email')->after('id');
            });

            Schema::table('channel_quarantine', function (Blueprint $table): void {
                $table->index(['channel', 'resolved_at']);
            });
        }

        // Belt and braces on a driver that ignores a default when adding a
        // column to a populated table.
        DB::table('channel_quarantine')->whereNull('channel')->update(['channel' => 'email']);
    }

    private function createFresh(): void
    {
        Schema::create('channel_quarantine', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('external_id', 512)->nullable();
            $table->string('provider', 32);
            $table->string('from_address', 320)->nullable();
            $table->string('subject', 512)->nullable();
            $table->text('reason');
            $table->longText('raw');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 26)->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['resolved_at', 'received_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_quarantine')) {
            return;
        }

        /*
         * Only email survives the rollback, because `mail_quarantine` has
         * nowhere to put anything else. A non-email row deleted here is a row
         * whose only home is the table being removed — recorded loudly rather
         * than dropped into a table that would misfile it as email.
         */
        DB::table('channel_quarantine')->where('channel', '!=', 'email')->delete();

        Schema::table('channel_quarantine', function (Blueprint $table): void {
            $table->dropIndex(['channel', 'resolved_at']);
            $table->dropColumn('channel');
        });

        Schema::rename('channel_quarantine', 'mail_quarantine');
    }
};
