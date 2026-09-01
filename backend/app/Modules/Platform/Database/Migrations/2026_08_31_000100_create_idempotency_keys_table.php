<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            // 40 chars holds a 26-char ULID or a 36-char UUID with room to spare.
            $table->string('key', 40)->primary();

            $table->string('actor_type', 16);
            $table->string('actor_id', 64)->nullable();

            // sha256 of method + path + body. A repeat key whose fingerprint
            // differs is a caller bug, not a retry, and must not replay.
            $table->char('request_fingerprint', 64);

            // Row lifecycle: in_flight -> completed. Distinct from the HTTP
            // status of the stored response, which lives in response_status.
            $table->string('status', 16)->index();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_headers')->nullable();
            $table->binary('response_body')->nullable();

            $table->timestamp('created_at');
            $table->timestamp('completed_at')->nullable();

            // The nightly prune scans by age.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
