<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What makes "find the answer we already wrote" take a second.
 *
 * TWO indexes over the same rows, and both are load-bearing.
 *
 * The tsvector is the one people expect: stemmed, weighted, ranked. It is
 * built per translation row with the configuration that matches that row's
 * LANGUAGE — `arabic` for an Arabic version, `english` for an English one —
 * rather than one neutral `simple` vector for both. `simple` does no stemming
 * at all, so "refunds" would not find an article titled "Refund"; the ticket
 * search uses it deliberately because a ticket's text is whatever a customer
 * typed and may be either language in one field, but an article translation is
 * known to be in exactly one.
 *
 * The trigram index is the one that gets removed by somebody tidying up, and
 * removing it breaks search for both languages in the same way.
 *
 * Measured rather than assumed, against this Postgres: the `arabic`
 * configuration DOES normalise the definite article and it DOES cope with a
 * hamza written or omitted and with ة against ه. What no stemmer can do is
 * match a word nobody has finished typing. `websearch_to_tsquery('arabic',
 * 'فوات')` returns nothing for an article titled "الفواتير"; `title ILIKE
 * '%فوات%'` returns it. The same holds in English — 'refun' finds nothing and
 * '%refun%' finds "Refund policy".
 *
 * That is not an edge case here. The in-ticket panel searches on every
 * debounced keystroke, so MOST of the queries this index answers are partial
 * words. Without trigrams the panel stays empty until the agent finishes a
 * word, which for a two-word Arabic title is most of the time they were
 * looking at it. `ArticleSearchPostgresTest` issues exactly those queries and
 * asserts the articles come back.
 *
 * Weighting: title 'A', body 'B'. An article TITLED "Refunds" must beat one
 * that mentions refunds in paragraph nine, and `ts_rank` only knows that if
 * the vector says which words came from where.
 *
 * Postgres only. SQLite has no tsvector and no pg_trgm; the query object falls
 * back to LIKE there, and the real path is covered by a test that skips loudly
 * rather than silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        if (! Schema::hasColumn('article_translations', 'search_vector')) {
            /*
             * The configuration is chosen from the row's own locale, inside
             * the generated expression. It has to be immutable for a generated
             * column, which `to_tsvector(regconfig, text)` is — the two-
             * argument form with a literal config. The CASE picks between two
             * literals rather than casting a column to `regconfig`, which
             * would not be immutable and which Postgres refuses.
             */
            DB::statement(<<<'SQL'
                ALTER TABLE article_translations
                ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    CASE WHEN locale = 'ar' THEN
                        setweight(to_tsvector('arabic', coalesce(title, '')), 'A') ||
                        setweight(to_tsvector('arabic', coalesce(body, '')), 'B')
                    ELSE
                        setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                        setweight(to_tsvector('english', coalesce(body, '')), 'B')
                    END
                ) STORED
            SQL);
        }

        foreach ([
            'CREATE INDEX IF NOT EXISTS article_translations_search_idx ON article_translations USING gin (search_vector)',

            /*
             * The half that makes Arabic work. Also what answers a partial
             * word typed into the in-ticket panel before somebody has finished
             * the query — which is most of the requests this endpoint sees.
             */
            'CREATE INDEX IF NOT EXISTS article_translations_title_trgm_idx ON article_translations USING gin (title gin_trgm_ops)',
            'CREATE INDEX IF NOT EXISTS article_translations_body_trgm_idx ON article_translations USING gin (body gin_trgm_ops)',

            // The customer-visible query: public AND published, newest first.
            'CREATE INDEX IF NOT EXISTS articles_visible_idx ON articles (internal_only, status, updated_at DESC)',
        ] as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'DROP INDEX IF EXISTS article_translations_search_idx',
            'DROP INDEX IF EXISTS article_translations_title_trgm_idx',
            'DROP INDEX IF EXISTS article_translations_body_trgm_idx',
            'DROP INDEX IF EXISTS articles_visible_idx',
        ] as $statement) {
            DB::statement($statement);
        }

        if (Schema::hasColumn('article_translations', 'search_vector')) {
            DB::statement('ALTER TABLE article_translations DROP COLUMN search_vector');
        }
    }
};
