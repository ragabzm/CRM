<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The customer record.
 *
 * No email or phone column. Contact details live in `contact_identifiers`
 * because a person has more than one, and a schema with `email` and `email2`
 * is a schema that will need `email3`.
 *
 * No organisation, company or account column either — deliberately, and the
 * absence is enforced by tests/Architecture/NoOrganisationFieldTest.php.
 *
 * `departments` is NOT created here. Security already owns that table, and one
 * product concept with two tables is two lists that drift apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Human-quotable. A customer reads this over the phone, so it is
            // short and unambiguous rather than the 26-character ULID.
            $table->string('reference', 20)->unique();

            $table->string('full_name', 200);

            // Grouping and filtering only, NEVER an access boundary. Whether an
            // agent may see a customer is a capability question; this column
            // says which team is likely to care about them.
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();

            $table->string('state', 16)->default('active');
            $table->string('preferred_channel', 16)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->timestamp('deactivated_at')->nullable();

            // The default list is "active customers, optionally in one
            // department", so that is the index it gets.
            $table->index(['state', 'department_id']);
            $table->index('updated_at');
        });

        $this->addStateConstraints();
        $this->addTrigramIndex();
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }

    /**
     * The enum values, enforced by the database as well as the application.
     *
     * A CHECK constraint is the difference between "the code always writes a
     * valid state" and "the column cannot hold an invalid one" — the second
     * survives a console command written in a hurry.
     */
    private function addStateConstraints(): void
    {
        // SQLite cannot add a CHECK after the fact, and the test suite runs on
        // it. The application-level enum still holds there.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_state_check CHECK (state IN ('active','inactive'))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_preferred_channel_check CHECK (preferred_channel IS NULL OR preferred_channel IN ('email','phone'))");
    }

    /**
     * Trigram index for partial-name search.
     *
     * Postgres-only, and the search has a portable fallback for other drivers —
     * see CustomerSearch. Without this index the similarity scan is a sequential
     * read of the whole table, which is fine at a thousand rows and not at a
     * hundred thousand.
     */
    private function addTrigramIndex(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX customers_full_name_trgm ON customers USING GIN (full_name gin_trgm_ops)');
    }
};
