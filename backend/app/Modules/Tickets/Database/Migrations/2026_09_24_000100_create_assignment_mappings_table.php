<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A lookup table, not a rule engine.
 *
 * Each row is one sentence: this category, or this department, goes to that
 * agent, or that department. There is no condition, no operator, no ordering
 * and no priority — and the reason there is none is the unique index below.
 *
 * `unique(source_type, source_id)` is what makes "at most one mapping per
 * source" STRUCTURAL rather than a validation somebody can relax in a hurry.
 * With two rows for one category you would need ordering to decide between
 * them; with ordering you need a way to see why a ticket went where it did;
 * and at that point this is the workflow engine the story says it is not.
 *
 * `target_type` is likewise closed: an agent or a department. "A queue", "the
 * least busy agent" and "whoever is on shift" each need an availability model
 * that was removed with chat presence, and each is named in the story as
 * deferred.
 */
return new class extends Migration
{
    /** What a mapping can be keyed on. */
    private const SOURCES = ['category', 'department'];

    /** Where a mapping can send a ticket. */
    private const TARGETS = ['agent', 'department'];

    public function up(): void
    {
        Schema::create('assignment_mappings', function (Blueprint $table): void {
            $table->id();

            $table->string('source_type', 16);
            /*
             * An integer id, and both sources happen to use integer keys —
             * `ticket_categories.id` and `departments.id`. Not a foreign key,
             * because one column cannot point at two tables; the editor
             * validates existence and `AssignmentMappings` skips a row whose
             * source has since gone.
             */
            $table->unsignedBigInteger('source_id');

            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');

            $table->timestamps();

            // The whole reason there is no ordering. See the class note.
            $table->unique(['source_type', 'source_id']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        /*
         * The two vocabularies, enforced by the database as well as by PHP.
         * Built from the arrays above so a new source or target is one edit —
         * a hand-copied list has silently blocked writes three times in this
         * schema already; see `EnumBackedCheckConstraintsTest`.
         */
        $quote = static fn (array $values): string => implode(',', array_map(
            static fn (string $v): string => "'".$v."'",
            $values,
        ));

        DB::statement('ALTER TABLE assignment_mappings ADD CONSTRAINT assignment_mappings_source_type_check CHECK (source_type IN ('.$quote(self::SOURCES).'))');
        DB::statement('ALTER TABLE assignment_mappings ADD CONSTRAINT assignment_mappings_target_type_check CHECK (target_type IN ('.$quote(self::TARGETS).'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_mappings');
    }
};
