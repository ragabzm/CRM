<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the chatbot gave up and asked for a person.
 *
 * The column exists to answer one question the waiting list has to ask: is
 * anybody actually waiting for a human? A conversation the chatbot is still
 * answering is not — putting it on the list would have agents opening chats
 * that have already been dealt with, which is the fastest way to make a
 * waiting list nobody trusts.
 *
 * Every conversation ends up handed off or finished. When the chatbot
 * capability is switched off, the first message hands off immediately — so
 * the waiting list never has to ask what the setting says, and a setting
 * changed mid-conversation cannot strand somebody.
 *
 * Backfilled for the conversations that already exist: they were created
 * before there was a chatbot, so nobody was ever going to answer them but a
 * person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->timestamp('handed_off_at')->nullable()->after('taken_at');
        });

        /*
         * Existing rows were waiting for a human by definition. Leaving them
         * null would drop every live conversation off the waiting list the
         * moment this deploys.
         */
        DB::table('chat_conversations')->whereNull('handed_off_at')->update([
            'handed_off_at' => DB::raw('created_at'),
        ]);

        Schema::table('chat_conversations', function (Blueprint $table): void {
            // The waiting list: handed off, untaken, unfinished, oldest first.
            $table->index(['handed_off_at', 'taken_by'], 'chat_conversations_handoff_index');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropIndex('chat_conversations_handoff_index');
            $table->dropColumn('handed_off_at');
        });
    }
};
