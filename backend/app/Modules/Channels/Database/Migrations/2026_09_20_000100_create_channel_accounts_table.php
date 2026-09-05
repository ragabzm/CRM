<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per configured way in.
 *
 * A support mailbox and a billing mailbox are two accounts on one channel, and
 * they route to two departments — which is the whole reason this table is not
 * just a list of channel types. The web form is an account too: exactly one,
 * because there is exactly one form.
 *
 * `is_active` is the switch an administrator throws. Disabling stops NEW
 * inbound; it does not touch the tickets the account already produced, which
 * stay fully workable. Deleting the row would take their provenance with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // `email` | `web_form` | `whatsapp` | `sms` | `chat`
            $table->string('channel', 32);
            $table->string('name');

            /*
             * Provider settings, per channel, as a bag rather than columns.
             * A mailbox needs a host and a secret; a WhatsApp number needs a
             * business id. Modelling both as columns means a table that is
             * three-quarters null for every row.
             */
            $table->json('provider_config')->nullable();

            $table->boolean('is_active')->default(true);

            // Integer, like every other department reference: `departments`
            // uses an auto-increment key, not a ULID.
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->timestamps();

            // One account per name per channel: two mailboxes called "Support"
            // is a configuration mistake, not a valid state.
            $table->unique(['channel', 'name']);
            $table->index(['channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_accounts');
    }
};
