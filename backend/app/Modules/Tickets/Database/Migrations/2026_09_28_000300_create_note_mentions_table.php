<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a note named, resolved once, at the moment it was written.
 *
 * The resolution is STORED rather than recomputed, and that is the point of
 * the table. Re-parsing the body on every render would mean a note quietly
 * naming somebody different after a rename, and a list of "notes that named
 * you" that changes when the user table does. What was said, was said.
 *
 * It also keeps the client out of it. The mentioned users are whatever the
 * server found in the body it stored — never a list the browser sent alongside
 * it, which anybody can edit into a notification to a colleague who was never
 * mentioned.
 *
 * `read_at` is per mention rather than per note: two people named in the same
 * note each dismiss their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_mentions', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('message_id', 26);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Denormalised so the Home tab lists mentions without joining the
            // whole conversation table to find which ticket each one is on.
            $table->string('ticket_id', 26);

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Naming somebody twice in one note is one mention.
            $table->unique(['message_id', 'user_id']);
            // The Home tab: my unread mentions, newest first.
            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_mentions');
    }
};
