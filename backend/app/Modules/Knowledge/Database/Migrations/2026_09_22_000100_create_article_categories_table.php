<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where articles are filed. Its own list, not the ticket one.
 *
 * An article about refunds and a ticket about refunds are related by MEANING,
 * not by a foreign key. Sharing the table looks tidy for a week and then a
 * support lead renames a ticket category and silently refiles forty articles,
 * or a knowledge category nobody wants on the new-ticket form appears on it.
 *
 * Flat, and staying flat. There is no `parent_id`: nesting is a tree, a tree
 * needs a move-to-parent action, a depth limit and a cycle check, and none of
 * that is in this story.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_categories', function (Blueprint $table): void {
            $table->id();

            // Both languages as columns, mirroring ticket_categories: the list
            // is sorted and searched per language.
            $table->string('name_en', 120);
            $table->string('name_ar', 120);

            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_categories');
    }
};
