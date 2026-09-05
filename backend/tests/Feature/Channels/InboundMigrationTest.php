<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The old mail tables folded into the shared ones, and did not survive it.
 *
 * Two tables holding the same messages is the state where one of them silently
 * stops being written to and nobody notices for a month, and where "have we
 * seen this message?" has two answers.
 */
final class InboundMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_mail_tables_are_gone(): void
    {
        $this->assertFalse(Schema::hasTable('mail_inbound'), 'mail_inbound still exists.');
        $this->assertFalse(Schema::hasTable('mail_quarantine'), 'mail_quarantine still exists.');

        $this->assertTrue(Schema::hasTable('inbound_messages'));
        $this->assertTrue(Schema::hasTable('channel_quarantine'));
        $this->assertTrue(Schema::hasTable('channel_accounts'));
    }

    public function test_a_message_id_can_only_be_claimed_once_per_channel(): void
    {
        $row = static function (string $channel, string $id): array {
            return [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'channel' => $channel,
                'provider_message_id' => $id,
                'direction' => 'inbound',
                'delivery_state' => 'received',
                'sender_identifier' => 'someone@example.test',
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        };

        DB::table('inbound_messages')->insert($row('email', 'shared-id'));

        /*
         * The SAME id on a DIFFERENT channel is a different message. A provider
         * id is only unique within the provider that issued it, and keying on
         * the id alone would make a WhatsApp message silently swallow an email.
         */
        DB::table('inbound_messages')->insert($row('web_form', 'shared-id'));

        $this->assertSame(2, DB::table('inbound_messages')->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('inbound_messages')->insert($row('email', 'shared-id'));
    }

    public function test_the_quarantine_knows_which_channel_failed(): void
    {
        $this->assertTrue(Schema::hasColumn('channel_quarantine', 'channel'));
    }
}
