<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ways to reach a customer.
 *
 * A separate table rather than columns, because a person has several emails and
 * several phones and no schema with a fixed number of them is ever right.
 *
 * Both the raw value and a normalised form are stored. The raw one is what the
 * customer gave us and what we show back; the normalised one is what search and
 * duplicate detection compare, so "+44 20 7946 0958" and "020 7946 0958" can be
 * recognised as the same number without rewriting what anyone typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_identifiers', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Cascade: an identifier has no meaning without its customer. This
            // is the one place a hard delete is right, and it only fires if a
            // customer row is ever removed — which the product does not do.
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('kind', 16);
            $table->string('value', 200);
            $table->string('value_normalised', 200);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['customer_id', 'kind']);

            /*
             * Unique WITHIN a customer, not across them. Two people in a
             * household genuinely share a landline, and a global unique index
             * would make the second one unregisterable — which is why duplicate
             * detection offers rather than blocks.
             */
            $table->unique(['customer_id', 'kind', 'value_normalised'], 'contact_identifiers_unique_per_customer');

            // The duplicate-detection lookup, exactly as it is queried.
            $table->index(['kind', 'value_normalised']);
        });

        $this->addConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_identifiers');
    }

    private function addConstraints(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE contact_identifiers ADD CONSTRAINT contact_identifiers_kind_check CHECK (kind IN ('email','phone'))");
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX contact_identifiers_value_trgm ON contact_identifiers USING GIN (value_normalised gin_trgm_ops)');
    }
};
