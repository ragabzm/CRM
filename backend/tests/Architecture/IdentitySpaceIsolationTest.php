<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The two identity spaces meet only in configuration.
 *
 * Security (T1) owns staff auth; Portal (T4) owns customer accounts. Deptrac
 * already forbids Security → Portal on tier grounds, but the reverse direction
 * and the *reason* both deserve their own statement: the moment either module
 * imports the other's model, "which table is this credential in?" becomes a
 * runtime question again, and the structural guarantee the two tables give us
 * quietly becomes a convention.
 */
final class IdentitySpaceIsolationTest extends TestCase
{
    public function test_security_does_not_import_the_portal_account(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app/Modules/Security') as $file) {
            foreach (SourceScanner::imports($file) as $import) {
                if (str_starts_with($import, 'App\\Modules\\Portal\\')) {
                    $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file)." imports {$import}";
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_portal_does_not_import_the_staff_user(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app/Modules/Portal') as $file) {
            foreach (SourceScanner::imports($file) as $import) {
                if ($import === 'App\\Models\\User' || str_starts_with($import, 'App\\Modules\\Security\\')) {
                    $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file)." imports {$import}";
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_the_two_spaces_are_wired_together_only_in_config(): void
    {
        $config = (string) file_get_contents(SourceScanner::basePath('config/auth.php'));

        // config/ is outside app/Modules, which is what lets the two guards be
        // declared side by side without either module depending on the other.
        $this->assertStringContainsString('App\\Modules\\Portal\\Domain\\PortalAccount', $config);
        $this->assertStringContainsString('portal_accounts', $config);
    }

    public function test_no_discriminator_column_is_referenced_anywhere(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            $source = (string) file_get_contents($file);

            // "No is_staff column and no shared table with a discriminator."
            if (preg_match('/\bis_staff\b/', $source) === 1) {
                $offenders[] = str_replace(SourceScanner::basePath().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }
}
