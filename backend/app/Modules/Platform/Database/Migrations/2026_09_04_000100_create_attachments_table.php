<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Files attached to something, wherever that something lives.
 *
 * Owned by Platform (T0) so Customers (T2) and Tickets (T3) can both use it
 * without either depending on the other. That is why the owner is a
 * `(owner_type, owner_id)` pair and NOT a foreign key: a real FK would have to
 * point at one table, and a column per possible owner is the same coupling
 * written out longer.
 *
 * The cost is that the database cannot enforce that an owner exists, and that
 * cost is accepted deliberately — see the comment in AttachmentsController
 * about which owners can be checked and which cannot.
 *
 * `down()` drops the table but NOT the files. Deleting uploaded content
 * automatically on a rollback would make a reversible schema change an
 * irreversible data loss. Clean up by hand:
 *   rm -rf storage/app/attachments/{quarantine,clean}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('owner_type', 16);
            $table->string('owner_id', 26);

            // The name the person gave it, preserved exactly — including
            // non-Latin scripts. It is escaped where it is used, never here.
            $table->string('filename', 255);

            /*
             * Where the bytes are. Unique, because two rows pointing at one
             * object means deleting either takes the other's file with it.
             * The prefix encodes the state (quarantine/ or clean/), so the
             * filesystem and the row cannot disagree about whether something
             * has been scanned.
             */
            $table->string('stored_path', 512)->unique();

            $table->unsignedBigInteger('byte_size');

            // SERVER-SNIFFED, always. The client's claim is never stored.
            $table->string('mime_type', 127);

            $table->string('uploader_id', 26)->nullable();
            $table->timestamp('uploaded_at');

            $table->string('scan_status', 16)->default('pending');
            $table->json('scan_result')->nullable();
            $table->timestamp('scanned_at')->nullable();

            $table->timestamps();

            // "Everything attached to this record", which is the only way this
            // table is ever read.
            $table->index(['owner_type', 'owner_id']);
            // The scan job's queue: pending work, oldest first.
            $table->index(['scan_status', 'created_at']);
        });

        $this->addConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }

    /**
     * Closed sets, enforced by the database as well as the application.
     *
     * A `scan_status` the application does not recognise would be treated as
     * "not clean" by the download gate — safe, but invisible. The constraint
     * makes it impossible instead of merely unlikely.
     */
    private function addConstraints(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE attachments ADD CONSTRAINT attachments_owner_type_check CHECK (owner_type IN ('customer','ticket','message'))");
        DB::statement("ALTER TABLE attachments ADD CONSTRAINT attachments_scan_status_check CHECK (scan_status IN ('pending','clean','failed'))");
    }
};
