<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ticket category list.
 *
 * FLAT BY CONSTRUCTION: there is deliberately no `parent_id`. A nesting column
 * invites a tree, and a category tree is a taxonomy nobody maintains — agents
 * pick the first plausible leaf, reporting splits across siblings, and the
 * hierarchy stops meaning anything within a year. Making the schema incapable
 * of nesting is cheaper than the discipline of not using it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table): void {
            $table->id();

            // Both languages are first-class columns rather than a JSON blob:
            // the list is sorted and searched per language.
            $table->string('name_en', 120);
            $table->string('name_ar', 120);

            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_categories');
    }
};
