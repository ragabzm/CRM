<?php

declare(strict_types=1);

use App\Modules\Knowledge\Domain\Enum\ArticleStatus;
use App\Modules\Knowledge\Domain\Enum\ArticleType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An article, minus its words.
 *
 * The words live in `article_translations`, one row per language, because a
 * `title_ar` column makes a third language a migration and an Arabic-only
 * article a row half full of nulls.
 *
 * `has_been_published` is the load-bearing column. It is set once at the first
 * publish and never cleared, and it is what refuses a delete. It is
 * deliberately NOT derived from `published_at`: archiving and re-publishing
 * both move that timestamp, and a permanent rule computed from a value that
 * moves is a rule that eventually says the wrong thing.
 *
 * `internal_only` is an axis, not a state. Every combination with `status` is
 * expressible — an internal draft, an internal published article, a public
 * draft — and a customer sees one only when it is public AND published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('type', 16);

            $table->foreignId('category_id')->constrained('article_categories')->restrictOnDelete();

            /*
             * Internal by default. A new article is a draft somebody is still
             * writing, and the wrong default here is the one that puts an
             * unfinished answer in front of a customer.
             */
            $table->boolean('internal_only')->default(true);

            $table->string('status', 16)->default(ArticleStatus::Draft->value);

            /** The language served when the reader's own is missing. */
            $table->string('default_locale', 5)->default('en');

            $table->boolean('has_been_published')->default(false);

            $table->timestamp('published_at')->nullable();
            $table->string('published_by', 26)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('archived_by', 26)->nullable();
            $table->string('created_by', 26)->nullable();

            $table->timestamps();

            // The customer-visible query: public AND published.
            $table->index(['status', 'internal_only']);
            $table->index(['category_id']);
            $table->index(['type']);
        });

        $this->constrain();
    }

    /**
     * The enums, enforced by the database as well as by PHP.
     *
     * Built from the enum cases rather than typed out, so adding a type or a
     * state is one case and not two edits. Hand-copied lists of this kind have
     * silently blocked writes three times in this schema already; see
     * `EnumBackedCheckConstraintsTest`.
     */
    private function constrain(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $quote = static fn (array $values): string => implode(',', array_map(
            static fn (string $v): string => "'".$v."'",
            $values,
        ));

        DB::statement('ALTER TABLE articles ADD CONSTRAINT articles_type_check CHECK (type IN ('.$quote(ArticleType::values()).'))');
        DB::statement('ALTER TABLE articles ADD CONSTRAINT articles_status_check CHECK (status IN ('.$quote(ArticleStatus::values()).'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
