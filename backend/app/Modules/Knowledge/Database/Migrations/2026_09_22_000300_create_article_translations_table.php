<?php

declare(strict_types=1);

use App\Modules\Knowledge\Domain\Enum\ArticleLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The words, one row per language.
 *
 * Rows and not columns, which is the whole point: an Arabic-only article is a
 * single row rather than a record half full of nulls, and a third language is
 * a new row rather than a migration on a table that by then holds everything.
 *
 * `body` holds HTML that has ALREADY been through the sanitiser. Nothing
 * writes to this column without passing through it — see `HtmlSanitiser` and
 * the translations controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_translations', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('article_id')->constrained('articles')->cascadeOnDelete();

            $table->string('locale', 5);
            $table->string('title', 200);
            $table->longText('body');

            $table->timestamps();

            // One version per language per article. A second `ar` row would
            // make "which Arabic version?" a question with no answer.
            $table->unique(['article_id', 'locale']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE article_translations ADD CONSTRAINT article_translations_locale_check CHECK (locale IN ('
                .implode(',', array_map(static fn (string $v): string => "'".$v."'", ArticleLocale::values()))
                .'))'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('article_translations');
    }
};
