<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One place to write a note about a customer, not two.
 *
 * `customers.notes` — a free-text column on the record itself — shipped in
 * Story 3.1. Story 3.2 then built a proper notes table: one row per note, with
 * an author, a timestamp, an edit history and a moderation rule.
 *
 * Both survived, and the profile screen rendered BOTH under the same heading.
 * So a customer with a note written through the edit form showed a panel
 * saying "No notes yet." with the note visible directly underneath it, and a
 * colleague looking for what somebody had recorded had two places to look and
 * no way to know it.
 *
 * The column loses. It has no author, no timestamp and no history — the three
 * things that make a note worth reading a year later.
 *
 * Nothing is deleted: every non-empty value becomes a real note, attributed to
 * the system, dated to when the customer record was last touched, and marked
 * as migrated so nobody later mistakes it for something a person typed today.
 */
return new class extends Migration
{
    public function up(): void
    {
        $carried = 0;

        DB::table('customers')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($customers) use (&$carried): void {
                $rows = [];

                foreach ($customers as $customer) {
                    $body = trim((string) $customer->notes);

                    if ($body === '') {
                        continue;
                    }

                    $rows[] = [
                        'id' => (string) Str::ulid(),
                        'customer_id' => $customer->id,
                        /*
                         * No author id: nobody knows who typed it. The column
                         * never recorded one, and inventing an attribution
                         * would be worse than admitting the gap.
                         */
                        'author_id' => null,
                        'author_name' => 'Migrated from the customer record',
                        'body' => $body,
                        'created_at' => $customer->created_at ?? now(),
                        'updated_at' => $customer->updated_at ?? now(),
                    ];

                    $carried++;
                }

                if ($rows !== []) {
                    DB::table('customer_notes')->insert($rows);
                }
            });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->text('notes')->nullable();
        });

        /*
         * The notes stay in their table. Copying them back would duplicate
         * them across both places again, which is the state this migration
         * exists to end — and a rollback that recreates the bug is not a
         * rollback.
         */
    }
};
