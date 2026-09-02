<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The customer record has no organisation, company or account concept.
 *
 * It is out of scope by decision, not by oversight — and the way that decision
 * gets reversed is not a design discussion but somebody adding a "company"
 * column because a form they were building had a spare field. Modelling
 * organisations properly means hierarchy, membership, billing ownership and a
 * merge story; a lone string column looks like the same feature and delivers
 * none of it, while quietly becoming impossible to remove.
 *
 * When organisations are genuinely in scope, delete this test in the same
 * change that designs them.
 */
final class NoOrganisationFieldTest extends TestCase
{
    private const FORBIDDEN = ['organisation', 'organization', 'company'];

    public function test_the_customers_module_has_no_organisation_concept(): void
    {
        $offenders = [];
        $scanned = 0;

        foreach (SourceScanner::phpFiles('app/Modules/Customers') as $file) {
            $scanned++;
            // Code only: a comment saying "there is no organisation here" must
            // not read as an organisation field.
            $source = mb_strtolower(SourceScanner::codeOnly($file));

            foreach (self::FORBIDDEN as $word) {
                if (str_contains($source, $word)) {
                    $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file)." contains [{$word}]";
                }
            }
        }

        // Proves the sweep actually read files. A path typo would otherwise
        // make this test pass by scanning nothing at all.
        $this->assertGreaterThan(5, $scanned, 'The sweep found no source files to check.');

        $this->assertSame(
            [],
            $offenders,
            "Organisations are out of scope and a lone column is not a design:\n  ".implode("\n  ", $offenders),
        );
    }

    public function test_the_customers_tables_have_no_such_column(): void
    {
        $migrations = SourceScanner::phpFiles('app/Modules/Customers/Database/Migrations');

        // A schema is far harder to walk back than a class, so the migrations
        // are checked separately rather than relying on the sweep above.
        $this->assertNotEmpty($migrations, 'Expected the Customers module to own migrations.');

        foreach ($migrations as $file) {
            $source = mb_strtolower(SourceScanner::codeOnly($file));

            foreach ([...self::FORBIDDEN, 'account_id'] as $word) {
                $this->assertStringNotContainsString($word, $source, basename($file)." declares [{$word}].");
            }
        }
    }

    public function test_the_guard_would_catch_a_violation(): void
    {
        // A guard that cannot fail is not a guard. Written to a real file so
        // the tokeniser path is exercised, not just the string match.
        $fixture = tempnam(sys_get_temp_dir(), 'guard').'.php';
        file_put_contents($fixture, '<?php /* no organisation here */ $table->string("company_name");');

        $code = mb_strtolower(SourceScanner::codeOnly($fixture));
        unlink($fixture);

        // The declaration is caught...
        $this->assertStringContainsString('company', $code);
        // ...and the comment explaining the rule is not.
        $this->assertStringNotContainsString('organisation', $code);
    }

    /*
     * The FRONTEND half of this rule lives in
     * frontend/__tests__/architecture/no-organisation.test.ts.
     *
     * It cannot live here: backend/ must not reference frontend/ — the two are
     * separately deployable and `composer no-cross-import` fails the build on
     * any such reference. Two guards, one per side, is the shape the boundary
     * requires.
     */
}
