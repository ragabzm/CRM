<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a person was entered by somebody, or inferred from an email.
 *
 * An auto-created customer is first-class in every way that matters — they can
 * be searched, ticketed, replied to, and edited. The flag exists because the
 * RECORD is thinner than one a human filled in: the name is whatever was in the
 * From header, there is no department, and nobody has confirmed any of it.
 *
 * Knowing that is what lets an agent ask "is this the same Ahmed we already
 * have?" rather than trusting a row nobody checked. Without the flag, a
 * half-known record and a verified one look identical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('auto_created')->default(false)->after('state');

            /*
             * WHICH door they came through, not just that they did. When a
             * second automatic channel lands, "auto_created" alone stops being
             * enough to tell a mail-created record from a form-created one.
             */
            $table->string('created_via', 32)->nullable()->after('auto_created');

            // The agent's question: "what came in on its own that nobody has
            // looked at?"
            $table->index(['auto_created', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['auto_created', 'created_at']);
            $table->dropColumn(['auto_created', 'created_via']);
        });
    }
};
